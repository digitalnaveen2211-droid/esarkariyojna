<?php
define('ESY',1);define('ROOT',dirname(__DIR__));
function e($v):string{return htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
require ROOT.'/app/blog_longform_update.php';
function must(bool $x,string $m):void{if(!$x){fwrite(STDERR,"FAIL: $m\n");exit(1);}}
function reject(callable $f):void{try{$f();}catch(InvalidArgumentException $e){return;}must(false,'invalid input should be rejected');}
$blocks=[
 ['type'=>'h2','text'=>'Correct procedure'],
 ['type'=>'p','text'=>str_repeat('Important careful verification guidance. ',240)],
 ['type'=>'h3','text'=>'Example'],
 ['type'=>'list','items'=>['Use official channels, not OTP requests','Keep records']],
 ['type'=>'table','headers'=>['Issue','Next step'], 'rows'=>[['Bank verification','Contact bank']]],
];
$blocks=array_merge($blocks,$blocks,$blocks);
$update=['slug'=>'sample-blog','sections_hi'=>$blocks,'sections_en'=>$blocks,'source_urls'=>['https://pmkisan.gov.in/']];
$parsed=longform_patch_validate(['schema'=>'esy-blog-longform-v1','updates'=>[$update]]);
must(count($parsed)===1,'valid bilingual content accepted');
$render=longform_patch_render($parsed[0],'hi');
must(str_contains($render,'<!-- esy-longform-v1-start -->'),'marker present');
must(str_contains($render,'<table>')&&str_contains($render,'href="https://pmkisan.gov.in/"'),'safe table/sources');
$a=longform_patch_merge('<p>Existing article text</p>',$render);
$b=longform_patch_merge($a,$render);
must($a===$b,'idempotent importer');
must(str_contains($b,'Existing article text'),'original remains');
reject(fn()=>longform_patch_validate(['schema'=>'wrong','updates'=>[$update]]));
reject(fn()=>longform_patch_validate(['schema'=>'esy-blog-longform-v1','updates'=>[$update,$update]]));
reject(fn()=>longform_patch_validate(['schema'=>'esy-blog-longform-v1','updates'=>[array_replace($update,['source_urls'=>['http://bad.example/']])]]));
echo "Blog longform patch validation passed\n";
