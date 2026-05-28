<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?></title>
    <link rel="icon" href="/favicon.ico">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600&family=DM+Serif+Display&display=swap" rel="stylesheet">
    <script src="https://kit.fontawesome.com/86c0c1c09a.js" crossorigin="anonymous"></script>
    <?php
    $accent = $profile['theme_color'] ?? $book['color'] ?? '#1a6b4a';
    $rgb = implode(',', sscanf($accent, '#%02x%02x%02x') ?? [26,107,74]);
    $bizName = $details['business_name'] ?? $book['name'] ?? '';
    $vis = $visibility ?? [];
    function bpVis(array $v, string $f, bool $d = true): bool { return (bool)($v[$f] ?? $d); }
    ?>
    <style>
    *{box-sizing:border-box;margin:0;padding:0}
    body{font-family:'DM Sans',sans-serif;background:#f5f5f5;color:#1a1a1a;min-height:100vh}

    /* NAV */
    .bp-nav{background:#fff;border-bottom:1px solid #eee;padding:0 20px;display:flex;align-items:center;justify-content:space-between;height:52px;position:sticky;top:0;z-index:100}
    .bp-nav-logo{font-size:15px;font-weight:700;color:#1a6b4a;text-decoration:none;display:flex;align-items:center;gap:8px}
    .bp-nav-right a{color:<?= e($accent) ?>;text-decoration:none;font-weight:600;font-size:13px}

    /* HERO */
    .bp-hero{height:240px;background:linear-gradient(135deg,<?= e($accent) ?> 0%,<?= e($accent) ?>cc 100%);position:relative;overflow:hidden}
    <?php if (bpVis($vis,'banner') && !empty($profile['banner'])): ?>
    .bp-hero{background-image:url('<?= asset('uploads/'.$profile['banner']) ?>');background-size:cover;background-position:center}
    <?php endif; ?>
    .bp-hero::after{content:'';position:absolute;inset:0;background:linear-gradient(to bottom,transparent 30%,rgba(0,0,0,.3))}

    /* CONTAINER */
    .bp-container{max-width:900px;margin:0 auto;padding:0 20px 60px}

    /* CARD */
    .bp-card{background:#fff;border-radius:16px;box-shadow:0 2px 20px rgba(0,0,0,.08);margin-top:-70px;position:relative;z-index:2;overflow:hidden}

    /* HEADER */
    .bp-header{padding:0 32px 24px;display:flex;gap:20px;align-items:flex-end;flex-wrap:wrap}
    .bp-logo-wrap{margin-top:-50px;flex-shrink:0}
    .bp-logo{width:100px;height:100px;border-radius:16px;object-fit:cover;border:4px solid #fff;box-shadow:0 4px 16px rgba(0,0,0,.15);background:<?= e($accent) ?>;display:flex;align-items:center;justify-content:center;color:#fff;font-size:38px;font-weight:800;overflow:hidden}
    .bp-logo img{width:100%;height:100%;object-fit:cover;border-radius:12px}
    .bp-header-info{flex:1;padding-top:10px;min-width:200px}
    .bp-biz-name{font-family:'DM Serif Display',serif;font-size:26px;color:#1a1a1a;margin-bottom:4px}
    .bp-handle{font-size:13px;color:<?= e($accent) ?>;font-weight:600;margin-bottom:6px}
    .bp-tagline{font-size:14px;color:#555;margin-bottom:10px;line-height:1.5}
    .bp-meta-pills{display:flex;flex-wrap:wrap;gap:8px}
    .bp-pill{display:inline-flex;align-items:center;gap:5px;padding:4px 10px;background:#f0f0f0;border-radius:20px;font-size:12px;font-weight:500;color:#555}
    .bp-pill i{font-size:11px;color:<?= e($accent) ?>}
    .bp-header-actions{align-self:center;display:flex;gap:8px;flex-wrap:wrap;padding-top:8px}
    .bp-btn{display:inline-flex;align-items:center;gap:6px;padding:9px 16px;border-radius:8px;font-size:13px;font-weight:600;text-decoration:none;transition:all .15s;border:none;cursor:pointer;font-family:inherit}
    .bp-btn-primary{background:<?= e($accent) ?>;color:#fff}
    .bp-btn-secondary{background:#f0f0f0;color:#333}
    .bp-btn-secondary:hover{background:#e5e5e5}

    /* TABS */
    .bp-tabs{display:flex;border-bottom:1px solid #eee;padding:0 32px;overflow-x:auto;gap:0}
    .bp-tab{padding:12px 18px;font-size:14px;font-weight:600;color:#888;text-decoration:none;border-bottom:2px solid transparent;cursor:pointer;white-space:nowrap;transition:all .15s}
    .bp-tab.active,.bp-tab:hover{color:<?= e($accent) ?>}
    .bp-tab.active{border-bottom-color:<?= e($accent) ?>}

    /* BODY */
    .bp-body{display:grid;grid-template-columns:2fr 1fr;gap:24px;padding:24px 32px}
    .bp-main{display:flex;flex-direction:column;gap:20px}
    .bp-aside{display:flex;flex-direction:column;gap:20px}

    /* SECTIONS */
    .bp-section{border:1px solid #eee;border-radius:12px;overflow:hidden}
    .bp-sec-head{padding:14px 18px;background:#fafafa;border-bottom:1px solid #eee;display:flex;align-items:center;gap:10px}
    .bp-sec-head h3{font-size:14px;font-weight:700;color:#1a1a1a}
    .bp-sec-head i{color:<?= e($accent) ?>;font-size:15px;width:18px;text-align:center}
    .bp-sec-body{padding:18px}
    .bp-prose{font-size:14px;color:#444;line-height:1.7;white-space:pre-wrap}

    /* CONTACT */
    .bp-contact-item{display:flex;align-items:center;gap:10px;padding:8px 0;border-bottom:1px solid #f5f5f5;font-size:13px}
    .bp-contact-item:last-child{border-bottom:none}
    .bp-contact-item i{color:<?= e($accent) ?>;width:16px;text-align:center;flex-shrink:0}
    .bp-contact-item a{color:#333;text-decoration:none;word-break:break-all}
    .bp-contact-item a:hover{color:<?= e($accent) ?>}

    /* SOCIAL */
    .bp-social-item{display:flex;align-items:center;gap:10px;padding:8px 0;border-bottom:1px solid #f5f5f5;text-decoration:none;color:#333;font-size:13px;font-weight:500;transition:color .12s}
    .bp-social-item:last-child{border-bottom:none}
    .bp-social-item:hover{color:<?= e($accent) ?>}

    /* STATS */
    .bp-stat-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px}
    .bp-stat{background:rgba(<?= $rgb ?>,0.06);border:1px solid rgba(<?= $rgb ?>,0.15);border-radius:10px;padding:14px;text-align:center}
    .bp-stat .stat-val{font-size:20px;font-weight:800;color:<?= e($accent) ?>;font-family:'DM Serif Display',serif}
    .bp-stat .stat-lbl{font-size:11px;color:#888;margin-top:2px;text-transform:uppercase;letter-spacing:.05em}

    /* PHOTO GRID */
    .bp-photo-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:8px}
    .bp-photo{border-radius:8px;overflow:hidden;aspect-ratio:1}
    .bp-photo img{width:100%;height:100%;object-fit:cover}

    /* PAGE CONTENT */
    .bp-page-content{padding:24px 32px;font-size:14px;color:#444;line-height:1.8;white-space:pre-wrap}

    /* FOOTER BADGE */
    .bp-footer-badge{text-align:center;padding:16px;background:#fafafa;border:1px solid #eee;border-radius:12px;margin-top:4px}

    @media(max-width:700px){.bp-body{grid-template-columns:1fr}.bp-header{padding:0 20px 20px}.bp-body{padding:16px 20px}.bp-tabs{padding:0 20px}.bp-biz-name{font-size:22px}.bp-page-content{padding:16px 20px}}
    @media print{.bp-nav,.bp-header-actions,.no-print{display:none!important}body{background:#fff}.bp-card{box-shadow:none;margin-top:0;border-radius:0}.bp-hero{height:80px}}
    </style>
</head>
<body>

<nav class="bp-nav">
    <a href="/" class="bp-nav-logo">
        <img src="/assets/images/ByabsayeeLogo.png" style="height:20px;object-fit:contain" onerror="this.style.display='none'">
        Byabsayee
    </a>
    <div class="bp-nav-right">
        <?php if (auth()): ?><a href="/dashboard">Go to App →</a><?php else: ?><a href="/login">Sign In</a><?php endif; ?>
    </div>
</nav>

<div class="bp-hero"></div>

<div class="bp-container">
<div class="bp-card">

    <!-- Header -->
    <div class="bp-header">
        <div class="bp-logo-wrap">
            <div class="bp-logo">
                <?php if (bpVis($vis,'logo') && !empty($profile['logo'])): ?>
                <img src="<?= asset('uploads/'.$profile['logo']) ?>" alt="">
                <?php else: ?>
                <?= mb_substr($bizName,0,1) ?>
                <?php endif; ?>
            </div>
        </div>
        <div class="bp-header-info">
            <div class="bp-biz-name"><?= e($bizName) ?></div>
            <div class="bp-handle">@<?= e($handle) ?></div>
            <?php if (bpVis($vis,'tagline') && !empty($profile['tagline'])): ?>
            <div class="bp-tagline"><?= e($profile['tagline']) ?></div>
            <?php endif; ?>
            <div class="bp-meta-pills">
                <?php if (bpVis($vis,'industry') && !empty($profile['industry'])): ?>
                <span class="bp-pill"><i class="fa-solid fa-tag"></i> <?= e($profile['industry']) ?></span>
                <?php endif; ?>
                <?php if (bpVis($vis,'founded_year') && !empty($profile['founded_year'])): ?>
                <span class="bp-pill"><i class="fa-solid fa-calendar"></i> Est. <?= e($profile['founded_year']) ?></span>
                <?php endif; ?>
                <?php if (bpVis($vis,'employee_count') && !empty($profile['employee_count'])): ?>
                <span class="bp-pill"><i class="fa-solid fa-users"></i> <?= e($profile['employee_count']) ?> employees</span>
                <?php endif; ?>
                <?php if (!empty($profile['city'])): ?>
                <span class="bp-pill"><i class="fa-solid fa-location-dot"></i> <?= e($profile['city']) ?><?= !empty($profile['country'])?', '.e($profile['country']):'' ?></span>
                <?php endif; ?>
            </div>
        </div>
        <div class="bp-header-actions no-print">
            <?php if (bpVis($vis,'whatsapp') && !empty($profile['whatsapp'])): ?>
            <a href="https://wa.me/<?= e(preg_replace('/\D/','',$profile['whatsapp_country'].$profile['whatsapp'])) ?>" target="_blank" class="bp-btn bp-btn-primary">
                <i class="fa-brands fa-whatsapp"></i> WhatsApp
            </a>
            <?php endif; ?>
            <?php if (bpVis($vis,'email') && !empty($profile['email'])): ?>
            <a href="mailto:<?= e($profile['email']) ?>" class="bp-btn bp-btn-secondary">
                <i class="fa-solid fa-envelope"></i> Email
            </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Tabs -->
    <?php
    $showAbout   = bpVis($vis,'page_about') && !empty($profile['page_about']);
    $showTerms   = bpVis($vis,'page_terms') && !empty($profile['page_terms']);
    $showPrivacy = bpVis($vis,'page_privacy') && !empty($profile['page_privacy']);
    $showPhotos  = bpVis($vis,'photos') && !empty($photos);
    $activePage  = $_GET['page'] ?? 'home';
    ?>
    <div class="bp-tabs">
        <a href="?" class="bp-tab <?= $activePage==='home'?'active':'' ?>"><i class="fa-solid fa-house" style="margin-right:5px"></i> Home</a>
        <?php if ($showAbout):  ?><a href="?page=about"   class="bp-tab <?= $activePage==='about'?'active':'' ?>">About</a><?php endif; ?>
        <?php if ($showPhotos): ?><a href="?page=photos"  class="bp-tab <?= $activePage==='photos'?'active':'' ?>">Photos</a><?php endif; ?>
        <?php if ($showTerms):  ?><a href="?page=terms"   class="bp-tab <?= $activePage==='terms'?'active':'' ?>">Terms</a><?php endif; ?>
        <?php if ($showPrivacy):?><a href="?page=privacy" class="bp-tab <?= $activePage==='privacy'?'active':'' ?>">Privacy</a><?php endif; ?>
        <a href="?page=contact" class="bp-tab <?= $activePage==='contact'?'active':'' ?>">Contact</a>
    </div>

    <?php if ($activePage === 'home'): ?>
    <!-- HOME -->
    <div class="bp-body">
        <div class="bp-main">
            <?php if (bpVis($vis,'bio') && !empty($profile['bio'])): ?>
            <div class="bp-section">
                <div class="bp-sec-head"><i class="fa-solid fa-building"></i><h3>About</h3></div>
                <div class="bp-sec-body"><p class="bp-prose"><?= e($profile['bio']) ?></p></div>
            </div>
            <?php endif; ?>

            <?php if ($showPhotos): ?>
            <div class="bp-section">
                <div class="bp-sec-head"><i class="fa-solid fa-images"></i><h3>Photos</h3></div>
                <div class="bp-sec-body">
                    <div class="bp-photo-grid">
                        <?php foreach (array_slice($photos,0,6) as $ph): ?>
                        <div class="bp-photo"><img src="<?= asset('uploads/'.$ph) ?>" alt=""></div>
                        <?php endforeach; ?>
                    </div>
                    <?php if (count($photos)>6): ?>
                    <a href="?page=photos" style="display:block;text-align:center;margin-top:10px;color:<?= e($accent) ?>;font-size:13px;font-weight:600">View all <?= count($photos) ?> photos →</a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <div class="bp-aside">
            <!-- Quick stats -->
            <div class="bp-section">
                <div class="bp-sec-head"><i class="fa-solid fa-chart-bar"></i><h3>Quick Facts</h3></div>
                <div class="bp-sec-body">
                    <div class="bp-stat-grid">
                        <?php if (bpVis($vis,'founded_year') && !empty($profile['founded_year'])): ?>
                        <div class="bp-stat">
                            <div class="stat-val"><?= date('Y') - $profile['founded_year'] ?>+</div>
                            <div class="stat-lbl">Years Active</div>
                        </div>
                        <?php endif; ?>
                        <?php if (bpVis($vis,'employee_count') && !empty($profile['employee_count'])): ?>
                        <div class="bp-stat">
                            <div class="stat-val"><?= e(explode('-',$profile['employee_count'])[0]) ?></div>
                            <div class="stat-lbl">Employees</div>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php if (bpVis($vis,'ceo_name') && !empty($profile['ceo_name'])): ?>
                    <div style="margin-top:12px;padding-top:12px;border-top:1px solid #f0f0f0">
                        <div style="font-size:11px;color:#999;margin-bottom:4px;text-transform:uppercase;letter-spacing:.05em">CEO / Founder</div>
                        <div style="font-size:14px;font-weight:700"><?= e($profile['ceo_name']) ?></div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Contact -->
            <?php $hasContact = (bpVis($vis,'email')&&!empty($profile['email']))||(bpVis($vis,'phone')&&!empty($profile['phone']))||(bpVis($vis,'whatsapp')&&!empty($profile['whatsapp']))||(bpVis($vis,'address')&&!empty($profile['address']));
            if ($hasContact): ?>
            <div class="bp-section">
                <div class="bp-sec-head"><i class="fa-solid fa-address-card"></i><h3>Contact</h3></div>
                <div class="bp-sec-body" style="padding:8px 18px">
                    <?php if (bpVis($vis,'email') && !empty($profile['email'])): ?>
                    <div class="bp-contact-item"><i class="fa-solid fa-envelope"></i><a href="mailto:<?= e($profile['email']) ?>"><?= e($profile['email']) ?></a></div>
                    <?php endif; ?>
                    <?php if (bpVis($vis,'phone') && !empty($profile['phone'])): ?>
                    <div class="bp-contact-item"><i class="fa-solid fa-phone"></i><a href="tel:<?= e($profile['phone']) ?>"><?= e($profile['phone']) ?></a></div>
                    <?php endif; ?>
                    <?php if (bpVis($vis,'whatsapp') && !empty($profile['whatsapp'])): ?>
                    <div class="bp-contact-item"><i class="fa-brands fa-whatsapp"></i><a href="https://wa.me/<?= e(preg_replace('/\D/','',$profile['whatsapp_country'].$profile['whatsapp'])) ?>" target="_blank">WhatsApp</a></div>
                    <?php endif; ?>
                    <?php if (!empty($profile['website'])): ?>
                    <div class="bp-contact-item"><i class="fa-solid fa-globe"></i><a href="<?= e($profile['website']) ?>" target="_blank"><?= e(preg_replace('#^https?://#','',$profile['website'])) ?></a></div>
                    <?php endif; ?>
                    <?php if (bpVis($vis,'address') && !empty($profile['address'])): ?>
                    <div class="bp-contact-item"><i class="fa-solid fa-location-dot"></i><span><?= e(implode(', ',array_filter([$profile['address'],$profile['city'],$profile['country']]))) ?></span></div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Social -->
            <?php
            $socialIcons = ['social_facebook'=>['fa-facebook','Facebook'],'social_instagram'=>['fa-instagram','Instagram'],'social_twitter'=>['fa-x-twitter','Twitter'],'social_linkedin'=>['fa-linkedin','LinkedIn'],'social_youtube'=>['fa-youtube','YouTube'],'social_tiktok'=>['fa-tiktok','TikTok']];
            $hasSocial = bpVis($vis,'social') && array_filter(array_map(fn($k)=>$profile[$k]??'',$_keys=array_keys($socialIcons)));
            if ($hasSocial): ?>
            <div class="bp-section">
                <div class="bp-sec-head"><i class="fa-solid fa-share-nodes"></i><h3>Follow Us</h3></div>
                <div class="bp-sec-body" style="padding:8px 18px">
                    <?php foreach ($socialIcons as $field=>[$icon,$label]):
                        if (empty($profile[$field])) continue; ?>
                    <a href="<?= e($profile[$field]) ?>" target="_blank" class="bp-social-item">
                        <i class="fa-brands <?=$icon?>" style="font-size:16px;width:20px;text-align:center"></i> <?=$label?>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <?php if (bpVis($vis,'external_links') && !empty($exLinks)): ?>
            <div class="bp-section">
                <div class="bp-sec-head"><i class="fa-solid fa-link"></i><h3>Links</h3></div>
                <div class="bp-sec-body" style="padding:8px 18px">
                    <?php foreach ($exLinks as $el): ?>
                    <a href="<?= e($el['url']) ?>" target="_blank" class="bp-social-item">
                        <i class="fa-solid fa-arrow-up-right-from-square" style="font-size:13px;width:20px;text-align:center"></i> <?= e($el['label']) ?>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <div class="bp-footer-badge">
                <div style="font-size:11px;color:#bbb;margin-bottom:4px">Listed on</div>
                <a href="/" style="font-size:14px;font-weight:700;color:<?= e($accent) ?>;text-decoration:none">Byabsayee</a>
                <div style="font-size:11px;color:#ddd;margin-top:2px">Business Network</div>
            </div>
        </div>
    </div>

    <?php elseif ($activePage === 'photos' && $showPhotos): ?>
    <div class="bp-page-content">
        <div class="bp-photo-grid" style="grid-template-columns:repeat(auto-fill,minmax(180px,1fr))">
            <?php foreach ($photos as $ph): ?>
            <div class="bp-photo" style="aspect-ratio:4/3"><img src="<?= asset('uploads/'.$ph) ?>" alt=""></div>
            <?php endforeach; ?>
        </div>
    </div>

    <?php elseif ($activePage === 'about' && $showAbout): ?>
    <div class="bp-page-content"><?= nl2br(e($profile['page_about'])) ?></div>

    <?php elseif ($activePage === 'terms' && $showTerms): ?>
    <div class="bp-page-content"><?= nl2br(e($profile['page_terms'])) ?></div>

    <?php elseif ($activePage === 'privacy' && $showPrivacy): ?>
    <div class="bp-page-content"><?= nl2br(e($profile['page_privacy'])) ?></div>

    <?php elseif ($activePage === 'contact'): ?>
    <div class="bp-body" style="grid-template-columns:1fr">
        <div class="bp-section">
            <div class="bp-sec-head"><i class="fa-solid fa-phone"></i><h3>Get in Touch</h3></div>
            <div class="bp-sec-body" style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
                <?php if (bpVis($vis,'email') && !empty($profile['email'])): ?>
                <a href="mailto:<?= e($profile['email']) ?>" style="display:flex;align-items:center;gap:12px;padding:16px;border:1px solid #eee;border-radius:12px;text-decoration:none;color:#333;font-weight:600;transition:all .15s" onmouseover="this.style.borderColor='<?= e($accent) ?>'" onmouseout="this.style.borderColor='#eee'">
                    <i class="fa-solid fa-envelope" style="font-size:22px;color:<?= e($accent) ?>"></i>
                    <div><div style="font-size:13px;font-weight:700"><?= e($profile['email']) ?></div><div style="font-size:11px;color:#999">Email</div></div>
                </a>
                <?php endif; ?>
                <?php if (bpVis($vis,'whatsapp') && !empty($profile['whatsapp'])): ?>
                <a href="https://wa.me/<?= e(preg_replace('/\D/','',$profile['whatsapp_country'].$profile['whatsapp'])) ?>" target="_blank" style="display:flex;align-items:center;gap:12px;padding:16px;border:1px solid #eee;border-radius:12px;text-decoration:none;color:#333;font-weight:600;transition:all .15s" onmouseover="this.style.borderColor='<?= e($accent) ?>'" onmouseout="this.style.borderColor='#eee'">
                    <i class="fa-brands fa-whatsapp" style="font-size:22px;color:#25d366"></i>
                    <div><div style="font-size:13px;font-weight:700">WhatsApp</div><div style="font-size:11px;color:#999"><?= e($profile['whatsapp_country'].' '.$profile['whatsapp']) ?></div></div>
                </a>
                <?php endif; ?>
                <?php if (bpVis($vis,'phone') && !empty($profile['phone'])): ?>
                <a href="tel:<?= e($profile['phone']) ?>" style="display:flex;align-items:center;gap:12px;padding:16px;border:1px solid #eee;border-radius:12px;text-decoration:none;color:#333;font-weight:600">
                    <i class="fa-solid fa-phone" style="font-size:22px;color:<?= e($accent) ?>"></i>
                    <div><div style="font-size:13px;font-weight:700"><?= e($profile['phone']) ?></div><div style="font-size:11px;color:#999">Phone</div></div>
                </a>
                <?php endif; ?>
                <?php if (bpVis($vis,'address') && !empty($profile['address'])): ?>
                <div style="display:flex;align-items:center;gap:12px;padding:16px;border:1px solid #eee;border-radius:12px">
                    <i class="fa-solid fa-location-dot" style="font-size:22px;color:<?= e($accent) ?>"></i>
                    <div><div style="font-size:13px;font-weight:700"><?= e($profile['address']) ?></div><div style="font-size:11px;color:#999"><?= e(implode(', ',array_filter([$profile['city'],$profile['country']]))) ?></div></div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

</div><!-- /.bp-card -->
</div><!-- /.bp-container -->

</body>
</html>
