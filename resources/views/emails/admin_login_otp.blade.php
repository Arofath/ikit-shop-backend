<!DOCTYPE html>
<html>
<head>
    <title>Admin Login 2FA</title>
    </head>
<body>
    <div class="container">
        <h2>Admin Security Alert</h2>
        <p>A login attempt was made to your Admin account. Please use the following code to authorize this login:</p>
        
        <div class="otp-code">
            {{ $otpCode }}
        </div>
        
        <p>If you did not attempt to log in, please secure your account immediately!</p>
    </div>
</body>
</html>