<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Seven Ways website contact</title>
</head>
<body>
    <h1>New website contact message</h1>
    <dl>
        <dt>Name</dt>
        <dd>{{ $contact['name'] }}</dd>
        <dt>Phone</dt>
        <dd>{{ $contact['phone'] }}</dd>
        <dt>Email</dt>
        <dd>{{ $contact['email'] ?? '—' }}</dd>
        <dt>Branch</dt>
        <dd>{{ $contact['branch'] ?? '—' }}</dd>
        <dt>Subject</dt>
        <dd>{{ $contact['subject'] }}</dd>
    </dl>
    <p>{!! nl2br(e($contact['message'])) !!}</p>
</body>
</html>
