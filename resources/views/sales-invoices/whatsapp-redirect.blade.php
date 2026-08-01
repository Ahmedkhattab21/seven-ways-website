<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="refresh" content="0;url={{ $whatsappUrl }}">
    <title>جاري فتح واتساب</title>
</head>
<body>
    <p>جاري فتح محادثة واتساب…</p>
    <p><a href="{{ $whatsappUrl }}">اضغط هنا إذا لم يفتح واتساب تلقائيًا</a></p>
    <script>window.location.replace(@json($whatsappUrl));</script>
</body>
</html>
