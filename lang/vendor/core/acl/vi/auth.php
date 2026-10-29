<?php

return [
    'login' => [
        'username' => 'Email/Username',
        'email' => 'E-mail',
        'password' => 'Mật khẩu',
        'title' => 'User Login',
        'remember' => 'Remember me?',
        'login' => 'Sign in',
        'placeholder' => [
            'username' => 'Enter your username or email address',
            'email' => 'Enter your email address',
            'password' => 'Enter your password',
        ],
        'success' => 'Login successfully!',
        'fail' => 'Wrong username or password.',
        'not_active' => 'Your account has not been activated yet!',
        'banned' => 'This account is banned.',
        'logout_success' => 'Logout successfully!',
        'dont_have_account' => 'You don\'t have account on this system, please contact administrator for more information!',
    ],
    'forgot_password' => [
        'title' => 'Quên mật khẩu',
        'message' => '<p>Have you forgotten your password?</p><p>Please enter your email account. System will send a email with active link to reset your password.</p>',
        'submit' => 'Gửi',
    ],
    'reset' => [
        'new_password' => 'Mật khẩu mới',
        'password_confirmation' => 'Confirm new password',
        'email' => 'E-mail',
        'title' => 'Reset your password',
        'update' => 'Cập nhật',
        'wrong_token' => 'This link is invalid or expired. Please try using reset form again.',
        'user_not_found' => 'This username is not exist.',
        'success' => 'Reset password successfully!',
        'fail' => 'Token is invalid, the reset password link has been expired!',
        'reset' => [
            'title' => 'Email reset password',
        ],
        'send' => [
            'success' => 'A email was sent to your email account. Please check and complete this action.',
            'fail' => 'Can not send email in this time. Please try again later.',
        ],
        'new-password' => 'Mật khẩu mới',
        'placeholder' => [
            'new_password' => 'Enter your new password',
            'new_password_confirmation' => 'Confirm your new password',
        ],
    ],
    'email' => [
        'reminder' => [
            'title' => 'Email reset password',
        ],
    ],
    'password_confirmation' => 'Password confirm',
    'failed' => 'Failed',
    'throttle' => 'Throttle',
    'not_member' => 'Not a member yet?',
    'register_now' => 'Đăng ký',
    'lost_your_password' => 'Lost your password?',
    'login_title' => 'Quản trị viên',
    'login_via_social' => 'Đăng nhập bằng mạng xã hội',
    'back_to_login' => 'Quay lại trang đăng nhập',
    'sign_in_below' => 'Sign In Below',
    'languages' => 'Languages',
    'reset_password' => 'Đặt lại mật khẩu',
    'settings' => [
        'email' => [
            'title' => 'ACL',
            'description' => 'ACL email configuration',
            'templates' => [
                'password_reminder' => [
                    'title' => 'Reset password',
                    'description' => 'Send email to user when requesting reset password',
                    'subject' => 'Đặt lại mật khẩu',
                    'reset_link' => 'Reset password link',
                ],
            ],
        ],
    ],
];
