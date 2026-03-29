<!DOCTYPE html>
<html>
<head>
    <title>OTP Verification</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f4f4f4; padding: 20px; }
        .container { max-width: 600px; margin: 0 auto; background: #ffffff; padding: 30px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h2 { color: #333333; text-align: center; }
        .otp-code { font-size: 32px; font-weight: bold; color: #4CAF50; text-align: center; letter-spacing: 5px; margin: 20px 0; padding: 10px; background: #f9f9f9; border: 1px dashed #cccccc; border-radius: 5px; }
        p { color: #555555; line-height: 1.5; }
        .footer { margin-top: 30px; font-size: 12px; color: #999999; text-align: center; }
    </style>
</head>
<body>
    <div class="container">
        <h2>Welcome to Our Platform!</h2>
        <p>Hello,</p>
        <p>Thank you for registering. To complete your registration securely, please use the following One-Time Password (OTP) to verify your email address. This code is valid for 5 minutes.</p>
        
        <div class="otp-code">
            {{ $otpCode }}
        </div>
        
        <p>If you did not request this, please ignore this email.</p>
        
        <div class="footer">
            <p>&copy; {{ date('Y') }} Your Project Name. All rights reserved.</p>
        </div>
    </div>
</body>
</html>