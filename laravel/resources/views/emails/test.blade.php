<!-- resources/views/emails/admin_verify_email.blade.php -->

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Confirmez votre email</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            color: #333;
            line-height: 1.5;
        }
        a {
            color: #1a73e8;
            text-decoration: none;
        }
        a:hover {
            text-decoration: underline;
        }
        .container {
            max-width: 600px;
            margin: auto;
            padding: 1rem;
            border: 1px solid #eee;
            border-radius: 6px;
        }
    </style>
</head>
<body>
    <div class="container">
        <p>Bonjour {{ $name }},</p>
        <p>Merci de vous être inscrit sur IMOo Admin. Veuillez confirmer votre adresse email en cliquant sur le lien ci-dessous :</p>
        <p><a href="{{ $verificationUrl }}">Confirmer mon email</a></p>
        <p>Si vous n'avez pas demandé cette inscription, vous pouvez ignorer ce message.</p>
        <p>Bonne journée !</p>
    </div>
</body>
</html>
