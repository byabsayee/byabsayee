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
    $accent = $profile['profile_theme_color'] ?? '#1a6b4a';
    $accentRgb = implode(',', sscanf($accent, '#%02x%02x%02x') ?? [26,107,74]);

    // Visibility helper — must be defined here since this is a standalone page
    if (!function_exists('vis')) {
        function vis(array $vis, string $field, bool $default = false): bool {
            return (bool)($vis[$field] ?? $default);
        }
    }
    ?>
    <style>
    *{box-sizing:border-box;margin:0;padding:0}
    body{font-family:'DM Sans',sans-serif;background:#f5f5f5;color:#1a1a1a;min-height:100vh}

    /* ── Banner + Header ─────────────────────────────────────────────── */
    .pub-banner{height:220px;background:linear-gradient(135deg,<?= e($accent) ?> 0%,<?= e($accent) ?>aa 100%);background-size:cover;background-position:center;position:relative;overflow:hidden}
    .pub-banner::after{content:'';position:absolute;inset:0;background:linear-gradient(to bottom,transparent 40%,rgba(0,0,0,.25))}
    <?php if (!empty($profile['profile_banner'])): ?>
    .pub-banner{background-image:url('<?= asset('uploads/'.$profile['profile_banner']) ?>')}
    <?php endif; ?>

    .pub-container{max-width:860px;margin:0 auto;padding:0 20px 60px}
    .pub-card{background:#fff;border-radius:16px;box-shadow:0 2px 20px rgba(0,0,0,.08);overflow:hidden;margin-top:-80px;position:relative;z-index:2}

    .pub-header{padding:0 32px 24px;display:flex;gap:24px;align-items:flex-end;flex-wrap:wrap}
    .pub-avatar-wrap{margin-top:-48px;flex-shrink:0}
    .pub-avatar{width:110px;height:110px;border-radius:50%;object-fit:cover;border:4px solid #fff;box-shadow:0 4px 20px rgba(0,0,0,.15);background:<?= e($accent) ?>;display:flex;align-items:center;justify-content:center;color:#fff;font-size:40px;font-weight:700;overflow:hidden}
    .pub-avatar img{width:100%;height:100%;object-fit:cover;border-radius:50%}
    .pub-header-info{flex:1;padding-top:12px;min-width:200px}
    .pub-name{font-family:'DM Serif Display',serif;font-size:28px;font-weight:400;line-height:1.1;color:#1a1a1a;margin-bottom:4px}
    .pub-handle{font-size:14px;color:<?= e($accent) ?>;font-weight:600;margin-bottom:6px}
    .pub-headline{font-size:15px;color:#555;line-height:1.5;margin-bottom:10px}
    .pub-meta-pills{display:flex;flex-wrap:wrap;gap:8px}
    .pub-pill{display:inline-flex;align-items:center;gap:5px;padding:4px 10px;background:#f0f0f0;border-radius:20px;font-size:12px;font-weight:500;color:#555}
    .pub-pill i{font-size:11px;color:<?= e($accent) ?>}
    .pub-header-actions{display:flex;gap:8px;padding-top:8px;flex-wrap:wrap;align-self:center}
    .pub-btn{display:inline-flex;align-items:center;gap:6px;padding:9px 16px;border-radius:8px;font-size:13px;font-weight:600;text-decoration:none;transition:all .15s;border:none;cursor:pointer;font-family:inherit}
    .pub-btn-primary{background:<?= e($accent) ?>;color:#fff}
    .pub-btn-primary:hover{opacity:.9}
    .pub-btn-secondary{background:#f0f0f0;color:#333}
    .pub-btn-secondary:hover{background:#e5e5e5}

    /* ── Sections ─────────────────────────────────────────────────────── */
    .pub-body{display:grid;grid-template-columns:2fr 1fr;gap:24px;padding:0 32px 32px;align-items:start}
    .pub-main{display:flex;flex-direction:column;gap:20px}
    .pub-aside{display:flex;flex-direction:column;gap:20px}

    .pub-section{border:1px solid #eee;border-radius:12px;overflow:hidden}
    .pub-sec-head{padding:14px 18px;background:#fafafa;border-bottom:1px solid #eee;display:flex;align-items:center;gap:10px}
    .pub-sec-head h3{font-size:14px;font-weight:700;color:#1a1a1a}
    .pub-sec-head i{color:<?= e($accent) ?>;font-size:15px;width:18px;text-align:center}
    .pub-sec-body{padding:18px}

    /* about */
    .pub-bio{font-size:14px;color:#444;line-height:1.7}

    /* skills */
    .skill-tags{display:flex;flex-wrap:wrap;gap:8px}
    .skill-tag{padding:5px 12px;background:rgba(<?= $accentRgb ?>,0.08);color:<?= e($accent) ?>;border-radius:20px;font-size:13px;font-weight:600;border:1px solid rgba(<?= $accentRgb ?>,0.2)}

    /* education */
    .edu-item{padding:12px 0;border-bottom:1px solid #f0f0f0;display:flex;gap:12px}
    .edu-item:last-child{border-bottom:none;padding-bottom:0}
    .edu-icon{width:36px;height:36px;border-radius:8px;background:rgba(<?= $accentRgb ?>,0.1);display:flex;align-items:center;justify-content:center;flex-shrink:0}
    .edu-icon i{color:<?= e($accent) ?>;font-size:14px}
    .edu-institute{font-size:14px;font-weight:700;color:#1a1a1a;margin-bottom:2px}
    .edu-subject{font-size:13px;color:#666;margin-bottom:2px}
    .edu-years{font-size:12px;color:#999}

    /* grades */
    .grade-row{display:flex;align-items:center;justify-content:space-between;padding:10px 0;border-bottom:1px solid #f0f0f0}
    .grade-row:last-child{border-bottom:none}
    .grade-level{font-size:14px;font-weight:700;color:#1a1a1a}
    .grade-board{font-size:12px;color:#999;margin-top:2px}
    .grade-result{font-size:14px;font-weight:700;color:<?= e($accent) ?>;white-space:nowrap}

    /* contact card */
    .contact-item{display:flex;align-items:center;gap:10px;padding:8px 0;border-bottom:1px solid #f0f0f0;font-size:13px}
    .contact-item:last-child{border-bottom:none}
    .contact-item i{color:<?= e($accent) ?>;width:16px;text-align:center;font-size:14px;flex-shrink:0}
    .contact-item a{color:#333;text-decoration:none;word-break:break-all}
    .contact-item a:hover{color:<?= e($accent) ?>}

    /* social */
    .social-grid{display:flex;flex-direction:column;gap:0}
    .social-item{display:flex;align-items:center;gap:10px;padding:8px 0;border-bottom:1px solid #f0f0f0;text-decoration:none;color:#333;font-size:13px;font-weight:500;transition:color .12s}
    .social-item:last-child{border-bottom:none}
    .social-item:hover{color:<?= e($accent) ?>}
    .social-item i{font-size:16px;width:20px;text-align:center;color:inherit}

    /* business badge */
    .biz-badge{background:linear-gradient(135deg,rgba(<?= $accentRgb ?>,0.1),rgba(<?= $accentRgb ?>,0.05));border:1px solid rgba(<?= $accentRgb ?>,0.2);border-radius:10px;padding:14px 16px}
    .biz-badge .biz-name{font-size:15px;font-weight:700;color:#1a1a1a;margin-bottom:2px}
    .biz-badge .biz-desig{font-size:13px;color:<?= e($accent) ?>;font-weight:600;margin-bottom:4px}
    .biz-badge .biz-since{font-size:12px;color:#999}

    /* nav */
    .pub-nav{background:#fff;border-bottom:1px solid #eee;padding:0 20px;position:sticky;top:0;z-index:100;display:flex;align-items:center;justify-content:space-between}
    .pub-nav-logo{font-size:16px;font-weight:700;color:#1a6b4a;text-decoration:none;padding:14px 0;display:flex;align-items:center;gap:8px}
    .pub-nav-right{font-size:13px;color:#999}
    .pub-nav-right a{color:<?= e($accent) ?>;text-decoration:none;font-weight:600}

    /* print */
    @media print{
        .pub-nav,.pub-header-actions,.no-print{display:none!important}
        body{background:#fff}
        .pub-card{box-shadow:none;margin-top:0;border-radius:0}
        .pub-banner{height:80px;margin-bottom:0}
    }

    @media(max-width:700px){
        .pub-body{grid-template-columns:1fr}
        .pub-header{padding:0 20px 20px}
        .pub-body{padding:0 20px 20px}
        .pub-name{font-size:22px}
    }
    </style>
</head>
<body>

<nav class="pub-nav">
    <a href="/" class="pub-nav-logo">
        <img src="/assets/images/ByabsayeeLogo.png" style="height:22px;object-fit:contain" onerror="this.style.display='none'">
        Byabsayee
    </a>
    <div class="pub-nav-right">
        <?php if (auth()): ?>
        <a href="/dashboard">Go to App →</a>
        <?php else: ?>
        <a href="/login">Sign In</a>
    <?php endif; ?>
    </div>
</nav>

<!-- Banner -->
<div class="pub-banner"></div>

<div class="pub-container">
<div class="pub-card">

    <!-- Header -->
    <div class="pub-header">
        <div class="pub-avatar-wrap">
            <div class="pub-avatar">
                <?php if (!empty($user['avatar'])): ?>
                <img src="<?= asset('uploads/'.$user['avatar']) ?>" alt="">
                <?php else: ?>
                <?= mb_substr($user['name']??'U',0,1) ?>
                <?php endif; ?>
            </div>
        </div>
        <div class="pub-header-info">
            <?php if (vis($vis,'name',true)): ?>
            <div class="pub-name"><?= e($user['name'] ?? '') ?></div>
            <?php endif; ?>
            <div class="pub-handle">@<?= e($handle ?? '') ?></div>
            <?php if (!empty($profile['profile_cv_headline']) && vis($vis,'headline',true)): ?>
            <div class="pub-headline"><?= e($profile['profile_cv_headline']) ?></div>
            <?php endif; ?>
            <div class="pub-meta-pills">
                <?php if (!empty($profile['city']) && vis($vis,'address',true)): ?>
                <span class="pub-pill"><i class="fa-solid fa-location-dot"></i> <?= e($profile['city']) ?><?= !empty($profile['country']) ? ', '.e($profile['country']) : '' ?></span>
                <?php endif; ?>
                <?php if (!empty($profile['experience_years']) && vis($vis,'experience_years',true)): ?>
                <span class="pub-pill"><i class="fa-solid fa-briefcase"></i> <?= e($profile['experience_years']) ?> yrs exp</span>
                <?php endif; ?>
                <?php if (!empty($user['blood_group']) && vis($vis,'blood_group',true)): ?>
                <span class="pub-pill"><i class="fa-solid fa-droplet"></i> <?= e($user['blood_group']) ?></span>
                <?php endif; ?>
                <?php if (!empty($profile['relationship_status']) && vis($vis,'relationship_status',true)):
                    $relLabels = ['single'=>'Single','in_relationship'=>'In a Relationship','married'=>'Married','widowed'=>'Widowed','prefer_not'=>'—']; ?>
                <span class="pub-pill"><i class="fa-solid fa-heart"></i> <?= $relLabels[$profile['relationship_status']] ?? '' ?></span>
                <?php endif; ?>
            </div>
        </div>
        <div class="pub-header-actions no-print">
            <?php if (!empty($profile['public_email']) && vis($vis,'email',true)): ?>
            <a href="mailto:<?= e($profile['public_email']) ?>" class="pub-btn pub-btn-primary">
                <i class="fa-solid fa-envelope"></i> Email Me
            </a>
            <?php endif; ?>
            <?php if (!empty($user['whatsapp_number']) && vis($vis,'whatsapp_number',true)): ?>
            <a href="https://wa.me/<?= e(preg_replace('/\D/','',$user['whatsapp_country_code'].$user['whatsapp_number'])) ?>" target="_blank" class="pub-btn pub-btn-secondary">
                <i class="fa-brands fa-whatsapp"></i> WhatsApp
            </a>
            <?php endif; ?>
            <?php if (auth() && auth()['id'] == $userId): ?>
            <button onclick="window.print()" class="pub-btn pub-btn-secondary no-print">
                <i class="fa-solid fa-print"></i> Print CV
            </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Body -->
    <div class="pub-body">

    <!-- Main column -->
    <div class="pub-main">

        <?php if (!empty($profile['bio']) && vis($vis,'bio',true)): ?>
        <div class="pub-section">
            <div class="pub-sec-head"><i class="fa-solid fa-user"></i><h3>About</h3></div>
            <div class="pub-sec-body">
                <p class="pub-bio"><?= nl2br(e($profile['bio'])) ?></p>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($profile['expertise']) && vis($vis,'expertise',true)): ?>
        <div class="pub-section">
            <div class="pub-sec-head"><i class="fa-solid fa-code"></i><h3>Skills & Expertise</h3></div>
            <div class="pub-sec-body">
                <div class="skill-tags">
                    <?php foreach (array_map('trim', explode(',', $profile['expertise'])) as $skill):
                        if (!$skill) continue; ?>
                    <span class="skill-tag"><?= e($skill) ?></span>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($education) && vis($vis,'education',true)): ?>
        <div class="pub-section">
            <div class="pub-sec-head"><i class="fa-solid fa-graduation-cap"></i><h3>Education</h3></div>
            <div class="pub-sec-body" style="padding:12px 18px">
                <?php foreach ($education as $edu): ?>
                <div class="edu-item">
                    <div class="edu-icon"><i class="fa-solid fa-school"></i></div>
                    <div>
                        <div class="edu-institute"><?= e($edu['institute']) ?></div>
                        <?php if ($edu['subject']): ?><div class="edu-subject"><?= e($edu['subject']) ?></div><?php endif; ?>
                        <div class="edu-years">
                            <?= $edu['from_year'] ? e($edu['from_year']) : '?' ?>
                            — <?= $edu['is_current'] ? '<span style="color:var(--green,#1a6b4a);font-weight:600">Present</span>' : ($edu['to_year'] ? e($edu['to_year']) : '?') ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($grades) && vis($vis,'grades',true)): ?>
        <div class="pub-section">
            <div class="pub-sec-head"><i class="fa-solid fa-award"></i><h3>Academic Results</h3></div>
            <div class="pub-sec-body" style="padding:8px 18px">
                <?php foreach ($grades as $g): ?>
                <div class="grade-row">
                    <div>
                        <div class="grade-level"><?= e($g['level']) ?></div>
                        <div class="grade-board"><?= e($g['board']??'') ?><?= $g['year'] ? ' · '.e($g['year']) : '' ?></div>
                    </div>
                    <div class="grade-result"><?= e($g['result']??'—') ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($business && vis($vis,'business',true)): ?>
        <div class="pub-section">
            <div class="pub-sec-head"><i class="fa-solid fa-building"></i><h3>Work Experience</h3></div>
            <div class="pub-sec-body">
                <div class="biz-badge">
                    <div class="biz-name"><?= e($business['business_name'] ?? $business['name']) ?></div>
                    <?php if (!empty($profile['designation']) && vis($vis,'designation',true)): ?>
                    <div class="biz-desig"><?= e($profile['designation']) ?></div>
                    <?php endif; ?>
                    <?php if (!empty($profile['working_since']) && vis($vis,'working_since',true)): ?>
                    <div class="biz-since"><i class="fa-solid fa-calendar-days" style="margin-right:4px"></i>Since <?= date('M Y', strtotime($profile['working_since'])) ?></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

    </div><!-- /.pub-main -->

    <!-- Aside column -->
    <div class="pub-aside">

        <?php
        $hasContact = (vis($vis,'email',true) && !empty($profile['public_email']))
                   || (vis($vis,'phone',true) && !empty($profile['public_phone']))
                   || (vis($vis,'whatsapp_number',true) && !empty($user['whatsapp_number']))
                   || (vis($vis,'website',true) && !empty($profile['website']));
        if ($hasContact): ?>
        <div class="pub-section">
            <div class="pub-sec-head"><i class="fa-solid fa-address-card"></i><h3>Contact</h3></div>
            <div class="pub-sec-body" style="padding:8px 18px">
                <?php if (vis($vis,'email',true) && !empty($profile['public_email'])): ?>
                <div class="contact-item"><i class="fa-solid fa-envelope"></i><a href="mailto:<?= e($profile['public_email']) ?>"><?= e($profile['public_email']) ?></a></div>
                <?php endif; ?>
                <?php if (vis($vis,'phone',true) && !empty($profile['public_phone'])): ?>
                <div class="contact-item"><i class="fa-solid fa-phone"></i><a href="tel:<?= e($profile['public_phone']) ?>"><?= e($profile['public_phone']) ?></a></div>
                <?php endif; ?>
                <?php if (vis($vis,'whatsapp_number',true) && !empty($user['whatsapp_number'])): ?>
                <div class="contact-item">
                    <i class="fa-brands fa-whatsapp"></i>
                    <a href="https://wa.me/<?= e(preg_replace('/\D/','',$user['whatsapp_country_code'].$user['whatsapp_number'])) ?>" target="_blank">
                        <?= e($user['whatsapp_country_code'].' '.$user['whatsapp_number']) ?>
                    </a>
                </div>
                <?php endif; ?>
                <?php if (vis($vis,'website',true) && !empty($profile['website'])): ?>
                <div class="contact-item"><i class="fa-solid fa-globe"></i><a href="<?= e($profile['website']) ?>" target="_blank"><?= e(preg_replace('#^https?://#','',$profile['website'])) ?></a></div>
                <?php endif; ?>
                <?php if (vis($vis,'address',true) && (!empty($profile['city']) || !empty($profile['address']))): ?>
                <div class="contact-item"><i class="fa-solid fa-location-dot"></i><span><?= e(implode(', ', array_filter([$profile['address']??'',$profile['city']??'',$profile['country']??'']))) ?></span></div>
                <?php endif; ?>
                <?php if (vis($vis,'date_of_birth',true) && !empty($user['date_of_birth'])): ?>
                <div class="contact-item"><i class="fa-solid fa-cake-candles"></i><span><?= date('d M Y', strtotime($user['date_of_birth'])) ?></span></div>
                <?php endif; ?>
                <?php if (vis($vis,'gender',true) && !empty($user['gender'])): ?>
                <div class="contact-item"><i class="fa-solid fa-venus-mars"></i><span><?= ucfirst(str_replace('_',' ',$user['gender'])) ?></span></div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($social) && vis($vis,'social_links',true)): ?>
        <div class="pub-section">
            <div class="pub-sec-head"><i class="fa-solid fa-share-nodes"></i><h3>Social</h3></div>
            <div class="pub-sec-body" style="padding:8px 18px">
                <?php
                $socialIcons = ['linkedin'=>'fa-linkedin','github'=>'fa-github','facebook'=>'fa-facebook','instagram'=>'fa-instagram','twitter'=>'fa-x-twitter','youtube'=>'fa-youtube','tiktok'=>'fa-tiktok','behance'=>'fa-behance','dribbble'=>'fa-dribbble','medium'=>'fa-medium','stackoverflow'=>'fa-stack-overflow','website'=>'fa-globe'];
                foreach ($social as $sl): ?>
                <a href="<?= e($sl['url']) ?>" target="_blank" rel="noopener" class="social-item">
                    <i class="fa-brands <?= e($socialIcons[$sl['platform']] ?? 'fa-link') ?>"></i>
                    <?= e(ucfirst($sl['platform'])) ?>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Byabsayee badge -->
        <div style="text-align:center;padding:16px;background:#fafafa;border-radius:12px;border:1px solid #eee">
            <div style="font-size:11px;color:#999;margin-bottom:6px">Profile powered by</div>
            <a href="/" style="font-size:14px;font-weight:700;color:<?= e($accent) ?>;text-decoration:none">Byabsayee</a>
            <div style="font-size:11px;color:#bbb;margin-top:4px">Business & Professional Network</div>
        </div>

    </div><!-- /.pub-aside -->
    </div><!-- /.pub-body -->

</div><!-- /.pub-card -->
</div><!-- /.pub-container -->

</body>
</html>
