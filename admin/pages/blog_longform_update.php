<?php
defined('ESY') or exit;
require_perm('settings.manage');
require_perm('posts.publish');
require_once ROOT . '/app/blog_longform_update.php';
header('Cache-Control: private, no-store');
$error='';$success=$_SESSION['blog_longform_done']??null;unset($_SESSION['blog_longform_done']);
$pending=$_SESSION['blog_longform_pending']??null;
if($_SERVER['REQUEST_METHOD']==='POST'&&isset($_POST['preview_blog_longform'])){
    unset($_SESSION['blog_longform_pending']);
    $file=$_FILES['longform_json']??null;
    try {
        if(!$file||(int)$file['error']!==UPLOAD_ERR_OK||(int)$file['size']<100||(int)$file['size']>1800000||!is_uploaded_file($file['tmp_name']))throw new InvalidArgumentException('Choose a valid JSON file under 1.8 MB.');
        $raw=json_decode((string)file_get_contents($file['tmp_name']),true,40,JSON_THROW_ON_ERROR);
        $items=longform_patch_validate($raw);
        $rows=longform_patch_rows($items);
        $hashes=[];
        foreach($rows as $slug=>$row)$hashes[$slug]=hash('sha256',json_encode($row,JSON_UNESCAPED_UNICODE));
        $_SESSION['blog_longform_pending']=['items'=>$items,'hashes'=>$hashes,'uid'=>(int)current_user()['id'],'ts'=>time()];
        flash('Preview ready. No live blogs changed.');
        redirect('/admin/?p=blog_longform_update');
    } catch(Throwable $e) {$error=$e instanceof InvalidArgumentException||$e instanceof JsonException?$e->getMessage():'Preview failed; check server logs.';if($error==='Preview failed; check server logs.')error_log('Blog longform preview: '.$e->getMessage());}
}elseif($_SERVER['REQUEST_METHOD']==='POST'&&isset($_POST['cancel_blog_longform'])){
    unset($_SESSION['blog_longform_pending']);flash('Cancelled. Live blogs unchanged.');redirect('/admin/?p=blog_longform_update');
}elseif($_SERVER['REQUEST_METHOD']==='POST'&&isset($_POST['apply_blog_longform'])){
    try{
        $p=$_SESSION['blog_longform_pending']??null;
        if(!is_array($p)||$p['uid']!==(int)current_user()['id']||(int)$p['ts']<time()-1800)throw new InvalidArgumentException('Preview expired. Upload JSON again.');
        $items=longform_patch_validate($p['items']);
        db()->beginTransaction();
        $rows=longform_patch_rows($items);
        foreach($rows as $slug=>$row){
            if(!hash_equals((string)($p['hashes'][$slug]??''),hash('sha256',json_encode($row,JSON_UNESCAPED_UNICODE))))
                throw new InvalidArgumentException('Content changed since preview: '.$slug.'. Re-upload to avoid overwriting another edit.');
        }
        foreach($items as $item){
            $row=$rows[$item['slug']];
            $hi=longform_patch_merge($row['content_hi'],longform_patch_render($item,'hi'));
            $en=longform_patch_merge($row['content_en'],longform_patch_render($item,'en'));
            q("UPDATE posts SET content_hi=?,content_en=?,updated_at=NOW() WHERE id=? AND type='blog' AND status='published'",
                [$hi,$en,(int)$row['id']]);
        }
        db()->commit();
        foreach($items as $item)log_activity('blog_longform_update',$item['slug']);
        unset($_SESSION['blog_longform_pending']);
        $_SESSION['blog_longform_done']=count($items);
        flash(count($items).' published blogs expanded without changing URLs, SEO metadata, images or publication status.');
        redirect('/admin/?p=blog_longform_update');
    }catch(Throwable $e){
        if(db()->inTransaction())db()->rollBack();
        $error=$e instanceof InvalidArgumentException?$e->getMessage():'Update failed; changes rolled back. Check server logs.';
        if(!$e instanceof InvalidArgumentException)error_log('Blog longform update: '.$e->getMessage());
    }
}
$pending=$_SESSION['blog_longform_pending']??null;
if(is_array($pending)&&($pending['uid']??null)!==(int)current_user()['id']){$pending=null;unset($_SESSION['blog_longform_pending']);}
admin_header('Blog Long-form Update', 'blog_longform_update');
?>
<?php if($error):?><div class="alert err"><?=e($error)?></div><?php endif;?>
<?php if($success):?><div class="alert ok"><?= (int)$success ?> published blogs updated. Review the public Hindi and English URLs.</div><?php endif;?>
<div class="card">
<h2>Expand EXISTING published blogs — safe JSON update</h2>
<p>Use only an <code>esy-blog-longform-v1</code> file. This does <strong>not</strong> create drafts, new posts or duplicate URLs. It updates <strong>only Hindi/English article content</strong>, appending a documented long-form extension. Existing title, summary, SEO title/description, featured banner, category, author and published status stay unchanged.</p>
<p>Workflow: Upload → preview each exact published blog → confirm. This page cannot modify schemes or other blogs. Re-import of same file replaces only its own marked supplement section rather than duplicating it.</p>
<form method="post" enctype="multipart/form-data">
<?=csrf_field()?>
<input type="file" name="longform_json" accept=".json,application/json" required>
<button class="btn primary" name="preview_blog_longform" value="1">Preview long-form updates</button>
</form>
</div>
<?php if(is_array($pending)&&!empty($pending['items'])):?>
<div class="card"><h2>Review <?=count($pending['items'])?> existing published blog updates</h2>
<p>Verify these are the intended posts. Main content and SEO metadata from CMS will be preserved. The extension is placed before existing related guides and official-source footer when available. Always review official claims before applying.</p>
<div class="tbl-wrap"><table class="tbl">
<thead><tr><th>Existing post</th><th>Hindi extension</th><th>English extension</th><th>Featured banner</th></tr></thead><tbody>
<?php foreach($pending['items'] as $it):
$h=blog_bundle_render_blocks($it['sections_hi']);$en=blog_bundle_render_blocks($it['sections_en']);
$found=q_one("SELECT id,image FROM posts WHERE type='blog' AND slug=? AND status='published'",[$it['slug']]); ?>
<tr>
<td><strong><?=e($it['slug'])?></strong><p><a target="_blank" rel="noopener" href="/hi/blog/<?=e($it['slug'])?>">Current HI page ↗</a> · <a target="_blank" rel="noopener" href="/en/blog/<?=e($it['slug'])?>">EN ↗</a></p></td>
<td><?=number_format(count(preg_split('/\\s+/u',trim(strip_tags($h))) ?: []))?> approximate Latin-space tokens; <?=mb_strlen(strip_tags($h))?> characters</td>
<td><?=number_format(count(preg_split('/\\s+/u',trim(strip_tags($en))) ?: []))?> words; <?=mb_strlen(strip_tags($en))?> characters</td>
<td><?= !empty($found['image'])?'✓ Preserved':'No current banner' ?></td>
</tr>
<?php endforeach;?></tbody></table></div>
<form method="post" onsubmit="return confirm('Expand these already-published blogs? Content changes immediately go live after confirmation. Database backup recommended.')">
<?=csrf_field()?>
<button class="btn primary" name="apply_blog_longform" value="1">Confirm update of <?=count($pending['items'])?> published blogs</button>
<button class="btn ghost" name="cancel_blog_longform" value="1">Cancel</button>
</form></div>
<?php endif;?>
<?php admin_footer();
