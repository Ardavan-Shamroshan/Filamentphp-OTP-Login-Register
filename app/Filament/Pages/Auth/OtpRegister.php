<?php

namespace App\Filament\Pages\Auth;

use App\Forms\Components\OtpInput;
use App\Models\Otp;
use App\Models\User;
use App\Services\OTPService;
use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use Filament\Events\Auth\Registered;
use Filament\Facades\Filament;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Wizard;
use Filament\Http\Responses\Auth\Contracts\RegistrationResponse;
use Filament\Notifications\Notification;
use Filament\Pages\Auth\Register;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;

class OtpRegister extends Register
{
    protected ?string    $maxWidth = '2xl';
    protected OTPService $otpService;

    public function __construct()
    {
        $this->otpService = app(OtpService::class);
    }

    protected function getForms(): array
    {
        return [
            'form' => $this->form(
                $this->makeForm()
                    ->schema([
                        Wizard::make([
                            Wizard\Step::make('registration')
                                ->label('ثبت نام')
                                ->schema([
                                    $this->getNameFormComponent(),
                                    $this->getMobileFormComponent(),
                                    $this->getPasswordFormComponent(),
                                    $this->getPasswordConfirmationFormComponent(),
                                ])
                                ->afterValidation(fn(array $state) => $this->afterRegisterValidation($state)),
                            Wizard\Step::make('verification')
                                ->label('تایید شماره تلفن')
                                ->schema([
                                    $this->getOtpCodeFormComponent(),
                                    $this->getOtpTokenFormComponent(),
                                ]),
                        ])
                            ->skippable(false)
                            ->nextAction(fn(Action $action) => $action->label('ارسال کد یکبار مصرف'))
                            ->submitAction(new HtmlString(Blade::render(<<<BLADE
                                    <x-filament::button type="submit" size="sm" wire:submit="register">
                                        ثبت نام
                                    </x-filament::button>
                                BLADE
                            )))
                    ])
                    ->statePath('data'),
            ),
        ];
    }

    public function register(): ?RegistrationResponse
    {
        try {
            $this->rateLimit(2);
        } catch (TooManyRequestsException $exception) {
            $this->getRateLimitedNotification($exception)?->send();

            return null;
        }

        $data = $this->form->getState();

        $user = User::query()->where('mobile', $data['mobile'])->first();

        event(new Registered($user));

        $this->verifyOtp($data);

        Filament::auth()->login($user);

        session()->regenerate();

        return app(RegistrationResponse::class);
    }

    public function verifyOtp(array $data)
    {
        $otp = Otp::query()->notExpiredToken($data['token'])->first();

        if (! $otp || $otp->code != $data['otp']) {
            Notification::make('invalid_code')
                ->danger()
                ->title('مشکلی بوجود آمده است')
                ->body('دیتای وارد شده نامعتبر است.')
                ->send();

            throw ValidationException::withMessages(['data.otp' => 'دیتای وارد شده نامعتبر است.']);
        }

        // expire otp
        $this->otpService->markCodeAsUsed($otp);
    }

    public function afterRegisterValidation(array $data)
    {
        $user = $this->wrapInDatabaseTransaction(function () use ($data) {
            $this->callHook('beforeValidate');

            $this->callHook('afterValidate');

            $data = $this->mutateFormDataBeforeRegister($data);

            $this->callHook('beforeRegister');

            $user = $this->handleRegistration($data);

            $this->form->model($user)->saveRelationships();

            $this->callHook('afterRegister');

            return $user;
        });

        if (! $user) {
            throw new Halt();
        }

        $this->sendOtp($user, $data);
    }

    protected function handleRegistration(array $data): Model
    {
        return $this->getUserModel()::firstOrCreate(
            ['mobile' => $data['mobile']],
            [
                'name'     => $data['name'],
                'password' => $data['password'],
            ]
        );
    }

    public function sendOtp(User $user, array $data)
    {
        // if (! $user->wasRecentlyCreated && ! $this->otpService->allowRequestOTP($user)) {
        //     Notification::make('too_many_requests')
        //         ->danger()
        //         ->title('مشکلی به وجود آمده است')
        //         ->body('تعداد درخواست ها بیش از حد مجاز است')
        //         ->send();
        //
        //     throw new Halt();
        // }

        $otp = $this->otpService->create($user)->first();
        $this->otpService->sendOTP($otp->code, $otp->login_id);

        Notification::make('otp_sent')
            ->success()
            ->title('موفق')
            ->body('کد یکبار مصرف با موفقیت به شماره تلفن شما ارسال شد.')
            ->send();

        $this->form->fill([
            'token'                => $otp->token,
            'password'             => $data['password'],
            'passwordConfirmation' => $data['passwordConfirmation'],
            'name'                 => $user->name,
            'mobile'               => $user->mobile
        ]);
    }

    protected function getMobileFormComponent(): Component
    {
        return TextInput::make('mobile')
            ->numeric()
            ->label('شماره موبایل')
            ->minLength(10)
            ->startsWith('09')
            ->required();
    }

    protected function getOtpCodeFormComponent(): Component
    {
        return OtpInput::make('otp')
            ->label('کد یکبار مصرف')
            ->maxLength(6)
            ->numberInput(6)
            ->required();
    }

    protected function getOtpTokenFormComponent(): Component
    {
        return Hidden::make('token')->required();
    }

    protected function getFormActions(): array
    {
        return [];
    }
}
