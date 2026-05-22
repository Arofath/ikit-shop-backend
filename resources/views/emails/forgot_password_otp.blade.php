@component('mail::message')
# Password Reset Request

Hello,

You are receiving this email because we received a password reset request for your account.

Your One-Time Password (OTP) for resetting your password is:

@component('mail::panel')
<div style="text-align: center; font-size: 24px; font-weight: bold; letter-spacing: 5px;">
{{ $otpCode }}
</div>
@endcomponent

*Note: This OTP will expire in 5 minutes.*

If you did not request a password reset, please ignore this email or contact support if you have concerns.

Thanks,<br>
{{ config('app.name') }}
@endcomponent