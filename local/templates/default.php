<?php
use Shaack\Reboot\Block;
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="description" content="">
    <meta name="author" content="">

    <title>Reboot CMS</title>

    <link href="/vendor/twbs/bootstrap/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="/local/assets/style/default.css" rel="stylesheet">
</head>
<body>
<?php
$navbar = new Block("navbar");
echo($navbar->render());
echo($this->article->render());
?>
<script src="/vendor/components/jquery/jquery.slim.js"></script>
<script src="/vendor/twbs/bootstrap/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
