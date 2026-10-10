<?php
$pageTitle = 'Business Profile — ' . e($book['name']);
ob_start();
$tab = $tab ?? 'info';
$externalLinks = $externalLinks ?? [];
$photos        = $photos ?? [];
$vis           = $visibility ?? [];

function bizVis(array $v, string $f, bool $d = true): bool { return (bool)($v[$f] ?? $d); }

$countryCodes = [
    '+880 BD','+91 IN','+92 PK','+971 AE','+966 SA','+1 US',
    '+44 GB','+61 AU','+49 DE','+33 FR','+81 JP','+86 CN',
    '+65 SG','+60 MY','+62 ID','+55 BR','+7 RU','+27 ZA',
];
?>
<style>
/* page-specific widgets (shell, forms, toggles, uploads, repeat rows come from settings.css) */
.page-editor{border:1px solid var(--border);border-radius:8px;overflow:hidden}
.page-editor-toolbar{padding:8px 12px;background:var(--bg);border-bottom:1px solid var(--border);display:flex;gap:6px;flex-wrap:wrap}
.page-editor-toolbar button{padding:4px 10px;border:1px solid var(--border);border-radius:6px;background:var(--card-bg);font-size:12px;cursor:pointer;font-family:inherit;color:var(--text)}
.page-editor-toolbar button:hover{background:var(--brand);color:#fff;border-color:var(--brand)}
.photo-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(100px,1fr));gap:10px;margin-bottom:12px}
.photo-thumb{border-radius:8px;overflow:hidden;aspect-ratio:1;background:var(--bg);border:1px solid var(--border);position:relative}
.photo-thumb img{width:100%;height:100%;object-fit:cover}
</style>


<div class="page-header">
    <div class="page-header-left">
        <div class="breadcrumb">
            <a href="/books/<?= $book['id'] ?>">Dashboard</a> <span>›</span>
            <a href="/books/<?= $book['id'] ?>/edit">Book Settings</a> <span>›</span>
            <span>Business Profile</span>
        </div>
        <h1><i class="fa-solid fa-briefcase" style="color:var(--brand)"></i> Business Profile</h1>
        <p>Public profile for <?= e($book['name']) ?></p>
    </div>
    <?php if ($handle): ?>
    <a href="/business/@<?= e($handle['handle']) ?>" target="_blank" class="btn btn-secondary btn-sm">
        <i class="fa-solid fa-arrow-up-right-from-square"></i> View Public Profile
    </a>
    <?php endif; ?>
</div>

<div class="st-wrap">
    <!-- Sidebar Nav -->
    <nav class="st-nav">
        <div class="st-nav-head">
            <div class="st-avatar" style="background:<?= e($book['color'] ?? '#1a6b4a') ?>"><?= e(mb_strtoupper(mb_substr($book['name']??'B',0,1))) ?></div>
            <div>
                <div class="st-nav-name"><?= e($book['name']) ?></div>
                <div class="st-nav-sub">Business Book</div>
            </div>
        </div>
        <div class="st-nav-group">Profile</div>
        <a href="?tab=info"       class="<?= $tab==='info'?'active':'' ?>"><i class="fa-solid fa-building"></i> Business Info</a>
        <a href="?tab=pages"      class="<?= $tab==='pages'?'active':'' ?>"><i class="fa-solid fa-file-lines"></i> Pages</a>
        <a href="?tab=social"     class="<?= $tab==='social'?'active':'' ?>"><i class="fa-solid fa-share-nodes"></i> Social & Links</a>
        <a href="?tab=photos"     class="<?= $tab==='photos'?'active':'' ?>"><i class="fa-solid fa-images"></i> Photos</a>
        <div class="st-nav-group">Privacy</div>
        <a href="?tab=visibility" class="<?= $tab==='visibility'?'active':'' ?>"><i class="fa-solid fa-eye"></i> Visibility</a>
        <div class="st-nav-group">Go Back</div>
        <a href="/books/<?= $book['id'] ?>/edit"><i class="fa-solid fa-arrow-left"></i> Book Settings</a>
    </nav>

    <!-- Content -->
    <div class="st-body">

    <?php if ($handle): ?>
    <div class="link-card">
        <i class="fa-solid fa-link"></i>
        <div class="link-text">
            <strong>Public Business Profile</strong>
            <a href="/business/@<?= e($handle['handle']) ?>" target="_blank">
                https://byabsayee.com/business/@<?= e($handle['handle']) ?>
            </a>
        </div>
        <button onclick="navigator.clipboard.writeText('https://byabsayee.com/business/@<?= e($handle['handle']) ?>');this.textContent='Copied!';setTimeout(()=>this.textContent='Copy',2000)" class="btn btn-sm btn-secondary">Copy</button>
    </div>
    <?php endif; ?>

    <?php if ($tab === 'info'): ?>
    <!-- ── BUSINESS INFO ────────────────────────────────────────────── -->
    <!-- Handle -->
    <div class="st-panel">
        <div class="st-head"><div><h2>Business Handle</h2><p class="panel-desc">Your unique @handle for the public business profile URL.</p></div></div>
        <form method="POST" action="/books/<?= $book['id'] ?>/business-profile/handle">
            <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
            <div class="fg">
                <label>Business Handle</label>
                <div style="display:flex;gap:8px;align-items:center">
                    <span style="font-size:22px;color:var(--brand);font-weight:700;flex-shrink:0">@</span>
                    <input type="text" name="handle" value="<?= e($handle['handle'] ?? '') ?>" placeholder="yourbusiness" pattern="[a-z0-9_]+" minlength="3" maxlength="50" required style="font-size:17px;font-weight:600">
                </div>
            </div>
            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check"></i> Save Handle</button>
        </form>
    </div>

    <!-- Core Info -->
    <div class="st-panel">
        <div class="st-head"><div><h2>Core Information</h2><p class="panel-desc">Basic business details shown on your public profile.</p></div></div>
        <form method="POST" action="/books/<?= $book['id'] ?>/business-profile/info" enctype="multipart/form-data">
            <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">

            <!-- Logo -->
            <div class="fg-row" style="margin-bottom:20px">
                <div class="fg">
                    <label>Business Logo <span class="hint">Recommended: Square, min 200×200px</span></label>
                    <div class="logo-zone" id="logoZone">
                        <?php if (!empty($profile['logo'])): ?>
                        <img src="<?= asset('uploads/'.$profile['logo']) ?>" class="logo-preview" id="logoPreview" style="display:block">
                        <?php else: ?>
                        <img class="logo-preview" id="logoPreview">
                        <i class="fa-solid fa-image" style="font-size:28px;color:var(--text-muted)"></i>
                        <div style="font-size:12px;color:var(--text-muted);margin-top:6px">Click to upload logo</div>
                        <?php endif; ?>
                        <input type="file" name="logo" accept="image/*" onchange="previewLogo(this)">
                    </div>
                </div>
                <div class="fg">
                    <label>Profile Banner <span class="hint">1200×300px recommended</span></label>
                    <div class="banner-zone" id="bannerZone">
                        <?php if (!empty($profile['banner'])): ?>
                        <img src="<?= asset('uploads/'.$profile['banner']) ?>" class="banner-preview" id="bannerPreview" style="display:block">
                        <?php else: ?>
                        <div class="banner-zone-inner">
                            <i class="fa-solid fa-panorama" style="font-size:24px;color:var(--text-muted)"></i>
                            <div style="font-size:12px;color:var(--text-muted);margin-top:6px">Click to upload banner</div>
                        </div>
                        <img class="banner-preview" id="bannerPreview">
                        <?php endif; ?>
                        <input type="file" name="banner" accept="image/*" onchange="previewBanner(this)">
                    </div>
                </div>
            </div>

            <div class="fg">
                <label>Tagline <span class="hint">Short slogan or description</span></label>
                <input type="text" name="tagline" value="<?= e($profile['tagline']??'') ?>" placeholder="e.g. Your trusted business partner since 2020">
            </div>

            <div class="fg">
                <label>About / Bio</label>
                <textarea name="bio" rows="4" placeholder="Describe your business..."><?= e($profile['bio']??'') ?></textarea>
            </div>

            <div class="fg-row">
                <div class="fg">
                    <label>Industry / Category</label>
                    <input type="text" name="industry" value="<?= e($profile['industry']??'') ?>" placeholder="e.g. Retail, Software, Food">
                </div>
                <div class="fg">
                    <label>Founded Year</label>
                    <input type="number" name="founded_year" value="<?= e($profile['founded_year']??'') ?>" min="1900" max="<?= date('Y') ?>" placeholder="e.g. 2015">
                </div>
            </div>

            <div class="fg-row">
                <div class="fg">
                    <label>CEO / Owner Name</label>
                    <input type="text" name="ceo_name" value="<?= e($profile['ceo_name']??'') ?>" placeholder="Full name">
                </div>
                <div class="fg">
                    <label>Number of Employees</label>
                    <select name="employee_count">
                        <option value="">— Select —</option>
                        <?php foreach (['1-5','6-10','11-25','26-50','51-100','101-250','250+'] as $ec): ?>
                        <option value="<?=$ec?>" <?= ($profile['employee_count']??'')===$ec?'selected':'' ?>><?=$ec?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <hr>
            <h3 style="font-size:14px;font-weight:700;margin:0 0 14px">Contact Details</h3>

            <div class="fg-row">
                <div class="fg">
                    <label>Business Email</label>
                    <input type="email" name="email" value="<?= e($profile['email']??'') ?>" placeholder="contact@business.com">
                </div>
                <div class="fg">
                    <label>Phone</label>
                    <input type="text" name="phone" value="<?= e($profile['phone']??'') ?>" placeholder="+880 01XXXXXXXXX">
                </div>
            </div>

            <div class="fg">
                <label>WhatsApp</label>
                <div class="phone-row">
                    <select name="whatsapp_country">
                        <?php foreach ($countryCodes as $cc): [$c,$n] = explode(' ',$cc,2);
                            $sel = ($profile['whatsapp_country']??'+880')===$c?'selected':''; ?>
                        <option value="<?= e($c) ?>" <?=$sel?>><?= e($c) ?> <?= e($n) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="text" name="whatsapp" value="<?= e($profile['whatsapp']??'') ?>" placeholder="01XXXXXXXXX">
                </div>
            </div>

            <div class="fg">
                <label>Website</label>
                <input type="url" name="website" value="<?= e($profile['website']??'') ?>" placeholder="https://yourwebsite.com">
            </div>

            <div class="fg-row">
                <div class="fg">
                    <label>Address</label>
                    <input type="text" name="address" value="<?= e($profile['address']??'') ?>" placeholder="Street address">
                </div>
                <div class="fg">
                    <label>City</label>
                    <input type="text" name="city" value="<?= e($profile['city']??'') ?>" placeholder="City">
                </div>
            </div>

            <div class="fg-row">
                <div class="fg">
                    <label>Country</label>
                    <input type="text" name="country" value="<?= e($profile['country']??'') ?>" placeholder="Country">
                </div>
                <div class="fg">
                    <label>Theme Color</label>
                    <div class="color-pick-row">
                        <div class="color-swatch" style="background:<?= e($profile['theme_color']??'#1a6b4a') ?>">
                            <input type="color" name="theme_color" value="<?= e($profile['theme_color']??'#1a6b4a') ?>"
                                   oninput="this.parentElement.style.background=this.value">
                        </div>
                        <span style="font-size:13px;color:var(--text-muted)">Profile accent color</span>
                    </div>
                </div>
            </div>

            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Save Business Info</button>
        </form>
    </div>

    <?php elseif ($tab === 'pages'): ?>
    <!-- ── PAGES ────────────────────────────────────────────────────── -->
    <div class="st-panel">
        <div class="st-head"><div><h2>Content Pages</h2><p class="panel-desc">Write your About, Terms of Service, and Privacy Policy pages. These will be shown as tabs on your public profile.</p></div></div>
        <form method="POST" action="/books/<?= $book['id'] ?>/business-profile/pages">
            <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
            <?php foreach (['page_about'=>'About Us','page_terms'=>'Terms of Service','page_privacy'=>'Privacy Policy'] as $field=>$label): ?>
            <div class="fg">
                <label><?= $label ?></label>
                <textarea name="<?= $field ?>" rows="10" placeholder="Write your <?= $label ?> content here... (Markdown supported)"><?= e($profile[$field]??'') ?></textarea>
            </div>
            <?php endforeach; ?>
            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Save Pages</button>
        </form>
    </div>

    <?php elseif ($tab === 'social'): ?>
    <!-- ── SOCIAL & LINKS ────────────────────────────────────────────── -->
    <div class="st-panel">
        <div class="st-head"><div><h2>Social Media</h2><p class="panel-desc">Add your business social media profiles.</p></div></div>
        <form method="POST" action="/books/<?= $book['id'] ?>/business-profile/social">
            <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
            <?php
            $socials = ['social_facebook'=>['fa-facebook','Facebook'],'social_instagram'=>['fa-instagram','Instagram'],'social_twitter'=>['fa-x-twitter','Twitter / X'],'social_linkedin'=>['fa-linkedin','LinkedIn'],'social_youtube'=>['fa-youtube','YouTube'],'social_tiktok'=>['fa-tiktok','TikTok']];
            foreach ($socials as $field=>[$icon,$label]):?>
            <div class="fg">
                <label><i class="fa-brands <?=$icon?>" style="color:var(--brand);margin-right:6px"></i><?=$label?></label>
                <input type="url" name="<?=$field?>" value="<?= e($profile[$field]??'') ?>" placeholder="https://...">
            </div>
            <?php endforeach; ?>
            <hr>
            <h3 style="font-size:14px;font-weight:700;margin:0 0 14px">External Links</h3>
            <div id="extLinkList">
                <?php foreach ($externalLinks as $el): ?>
                <div class="repeat-item">
                    <div class="item-fields" style="grid-template-columns:1fr 2fr">
                        <input type="text" name="ext_name[]" value="<?= e($el['label']) ?>" placeholder="Label">
                        <input type="url" name="ext_url[]" value="<?= e($el['url']) ?>" placeholder="https://...">
                    </div>
                    <button type="button" class="repeat-del" onclick="this.closest('.repeat-item').remove()"><i class="fa-solid fa-xmark"></i></button>
                </div>
                <?php endforeach; ?>
            </div>
            <button type="button" class="add-row-btn" onclick="addExtLink()"><i class="fa-solid fa-plus"></i> Add Link</button>
            <div style="margin-top:20px">
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Save Social & Links</button>
            </div>
        </form>
    </div>

    <?php elseif ($tab === 'photos'): ?>
    <!-- ── PHOTOS ────────────────────────────────────────────────────── -->
    <div class="st-panel">
        <div class="st-head"><div><h2>Photo Gallery</h2><p class="panel-desc">Upload photos to showcase your business. These appear in the Photos tab of your public profile.</p></div></div>
        <?php if (!empty($photos)): ?>
        <div class="photo-grid">
            <?php foreach ($photos as $photo): ?>
            <div class="photo-thumb">
                <img src="<?= asset('uploads/'.$photo) ?>" alt="">
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <form method="POST" action="/books/<?= $book['id'] ?>/business-profile/photos" enctype="multipart/form-data">
            <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
            <div class="fg">
                <label>Upload Photo</label>
                <input type="file" name="photo" accept="image/*" class="btn btn-secondary" style="padding:8px;cursor:pointer;width:auto">
                <div style="font-size:12px;color:var(--text-muted);margin-top:6px">JPG, PNG, WEBP · Max 5MB</div>
            </div>
            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-upload"></i> Upload Photo</button>
        </form>
    </div>

    <?php elseif ($tab === 'visibility'): ?>
    <!-- ── VISIBILITY ────────────────────────────────────────────────── -->
    <div class="st-panel">
        <div class="st-head"><div class="st-icon"><i class="fa-solid fa-eye"></i></div><div><h2>Public Visibility</h2><p class="panel-desc">Control what information is visible on your public business profile.</p></div></div>
        <form method="POST" action="/books/<?= $book['id'] ?>/business-profile/visibility">
            <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
            <?php
            $visFields = [
                'tagline'        => ['Tagline', 'Short business slogan'],
                'bio'            => ['About / Bio', 'Business description'],
                'logo'           => ['Logo', 'Business logo image'],
                'banner'         => ['Banner', 'Profile banner image'],
                'founded_year'   => ['Founded Year', 'Year established'],
                'ceo_name'       => ['CEO / Owner', 'Leadership info'],
                'employee_count' => ['Employee Count', 'Team size'],
                'industry'       => ['Industry', 'Business category'],
                'email'          => ['Email', 'Business email'],
                'phone'          => ['Phone', 'Business phone'],
                'whatsapp'       => ['WhatsApp', 'WhatsApp contact'],
                'address'        => ['Address', 'Physical location'],
                'social'         => ['Social Links', 'Social media profiles'],
                'external_links' => ['External Links', 'Other website links'],
                'page_about'     => ['About Page', 'Full About Us page'],
                'page_terms'     => ['Terms Page', 'Terms of Service'],
                'page_privacy'   => ['Privacy Policy', 'Privacy page'],
                'photos'         => ['Photo Gallery', 'Business photos'],
            ];
            foreach ($visFields as $field => [$label, $desc]): ?>
            <div class="vis-row">
                <div class="vis-info">
                    <strong><?=$label?></strong>
                    <span><?=$desc?></span>
                </div>
                <label class="toggle-switch">
                    <input type="checkbox" name="visible_<?=$field?>" value="1" <?= bizVis($vis,$field)?'checked':'' ?>>
                    <span class="toggle-slider"></span>
                </label>
            </div>
            <?php endforeach; ?>
            <div style="margin-top:20px">
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Save Visibility</button>
            </div>
        </form>
    </div>
    <?php endif; ?>

    </div><!-- /.st-body -->
</div><!-- /.st-wrap -->

<script>
function previewLogo(input) {
    if (!input.files || !input.files[0]) return;
    const reader = new FileReader();
    reader.onload = e => {
        const img = document.getElementById('logoPreview');
        img.src = e.target.result; img.style.display = 'block';
    };
    reader.readAsDataURL(input.files[0]);
}
function previewBanner(input) {
    if (!input.files || !input.files[0]) return;
    const reader = new FileReader();
    reader.onload = e => {
        const img = document.getElementById('bannerPreview');
        img.src = e.target.result; img.style.display = 'block';
    };
    reader.readAsDataURL(input.files[0]);
}
function addExtLink() {
    const list = document.getElementById('extLinkList');
    const div = document.createElement('div');
    div.className = 'repeat-item';
    div.innerHTML = `<div class="item-fields" style="grid-template-columns:1fr 2fr">
        <input type="text" name="ext_name[]" placeholder="Label (e.g. Menu, Map)">
        <input type="url" name="ext_url[]" placeholder="https://...">
    </div>
    <button type="button" class="repeat-del" onclick="this.closest('.repeat-item').remove()"><i class="fa-solid fa-xmark"></i></button>`;
    list.appendChild(div);
}
</script>

<?php
$content = ob_get_clean();
require BASE_PATH . '/views/partials/layout.php';
