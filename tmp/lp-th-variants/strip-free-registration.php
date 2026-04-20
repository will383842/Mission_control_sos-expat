<?php
require '/var/www/html/vendor/autoload.php';
$app = require '/var/www/html/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\LandingPage;

$replacements = [
    'en' => [
        'Free registration · Activation in under 5 minutes' => 'Open sign-up · Activation in under 5 minutes',
        'Free registration from 197 countries' => 'Open sign-up from 197 countries',
        'Free registration · Activation in under 5 minutes.' => 'Open sign-up · Activation in under 5 minutes.',
    ],
    'zh' => [
        '免费注册 · 5 分钟内激活' => '注册开放 · 5 分钟内激活',
        '从 197 个国家免费注册' => '从 197 个国家开放注册',
    ],
    'hi' => [
        'निःशुल्क पंजीकरण · 5 मिनट से कम में सक्रियण' => 'पंजीकरण खुला · 5 मिनट से कम में सक्रियण',
        '197 देशों से निःशुल्क पंजीकरण' => '197 देशों से खुला पंजीकरण',
    ],
    'ar' => [
        'تسجيل مجاني · تفعيل في أقل من 5 دقائق' => 'التسجيل متاح · تفعيل في أقل من 5 دقائق',
        'تسجيل مجاني من 197 دولة' => 'تسجيل متاح من 197 دولة',
    ],
];

$rows = LandingPage::whereIn('parent_id', [716, 717])
    ->whereIn('language', ['en', 'zh', 'hi', 'ar'])
    ->get();

$fixed = 0;
foreach ($rows as $row) {
    $s = json_encode($row->sections, JSON_UNESCAPED_UNICODE);
    $changed = false;
    foreach ($replacements[$row->language] ?? [] as $from => $to) {
        if (str_contains($s, $from)) {
            $s = str_replace($from, $to, $s);
            $changed = true;
        }
    }
    if ($changed) {
        $row->sections = json_decode($s, true);
        $row->save();
        echo "fixed #{$row->id} {$row->language}\n";
        $fixed++;
    }
}
echo "total $fixed\n";
