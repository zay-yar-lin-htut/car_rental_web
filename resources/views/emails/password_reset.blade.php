<!DOCTYPE html>
<html lang="en" style="margin:0; padding:0;">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Password Notification - Journey Wheel</title>

  <!-- Google Font: Orbitron -->
  <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@500;700&display=swap" rel="stylesheet">

  <style>
    body {
      margin: 0;
      padding: 0;
      background-color: #f4f4f4;
      font-family: Arial, sans-serif;
    }
    .logo-text {
      font-family: 'Orbitron', sans-serif;
      font-size: 28px;
      letter-spacing: 1.5px;
      color: #00F5D4;
      margin: 0;
      text-transform: uppercase;
    }
    .info-box {
      background:#f4f4f4;
      border-radius:6px;
      padding:12px;
      display:inline-block;
      font-family:monospace;
      font-size:15px;
      word-break:break-word;
    }
    .danger {
      color:#B00020;
      font-weight:700;
    }
    a.cta {
      background-color:#00F5D4;
      color:#1B263B;
      text-decoration:none;
      padding:12px 25px;
      border-radius:6px;
      font-weight:bold;
      display:inline-block;
    }
  </style>
</head>

<body>

  <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color:#f4f4f4; padding:40px 0;">
    <tr>
      <td align="center">
        <table role="presentation" width="600" cellspacing="0" cellpadding="0" border="0" style="background-color:#ffffff; border-radius:12px; border:3px solid #00F5D4; overflow:hidden;">
          
          <!-- Header -->
          <tr>
            <td align="center" style="background-color:#1B263B; padding:25px;">
              <h1 class="logo-text">Journey Wheel</h1>
              <p style="color:#ffffff; margin-top:5px; font-size:14px;">Car Rental Made Easy</p>
            </td>
          </tr>

          <!-- Main Content -->
          <tr>
            <td style="padding:30px 40px; color:#333333;">
              <h2 style="color:#1B263B; margin-top:0;">Important: Account Password</h2>

              <p style="font-size:16px; line-height:1.6;">
                Hello <strong>{{ $user_name }}</strong>,
              </p>

              <p style="font-size:16px; line-height:1.6;">
                This message contains your current account password for <strong>Journey Wheel</strong>. For your security, you <span class="danger">must change this password immediately</span>.
              </p>

              <table role="presentation" style="margin-top:18px; margin-bottom:18px;">
                <tr>
                  <td style="padding:6px 12px; font-weight:bold; vertical-align:top;">Reseted Password:</td>
                  <td style="padding:6px 12px;"><span class="info-box">{{ $password }}</span></td>
                </tr>
              </table>

              <p style="font-size:16px; line-height:1.6;">
                Click the button below and go to your profile page, and update your password:
              </p>
            </td>
          </tr>

          <!-- CTA -->
          <tr>
            <td align="center" style="padding:18px 0 28px 0;">
              <a class="cta" href="https://car-rental-frontend-weu1.vercel.app/">Change Password Now</a>
            </td>
          </tr>

          <!-- Security Guidance -->
          <tr>
            <td style="padding:0 40px 24px 40px; color:#555555; font-size:14px; line-height:1.6;">
              <p style="margin:0 0 8px 0;">
                <strong>Security notice:</strong> If you did not request this or you believe your account has been compromised, immediately contact our support team:
                <br/>
                <a href="mailto:jourenywheel@gmail.com">jourenywheel@gmail.com</a> or call +959 973944946
              </p>
            </td>
          </tr>

          <!-- Footer -->
          <tr>
            <td align="center" style="background-color:#1B263B; padding:20px;">
              <p style="color:#ffffff; font-size:13px; margin:0;">
                © {{ $date }} Journey Wheel. All rights reserved.
              </p>
            </td>
          </tr>

        </table>
      </td>
    </tr>
  </table>

</body>
</html>
