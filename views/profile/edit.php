<?php
$pageTitle = 'My Profile';
$tab = $tab ?? 'basic';
ob_start();

$themeColor = $profile['profile_theme_color'] ?? '#1a6b4a';

// Define platforms at top level so the <script> block can always access it
$platforms = ['linkedin','github','facebook','instagram','twitter','youtube','tiktok','behance','dribbble','medium','stackoverflow','website'];
$platformIcons = ['linkedin'=>'fa-linkedin','github'=>'fa-github','facebook'=>'fa-facebook','instagram'=>'fa-instagram','twitter'=>'fa-x-twitter','youtube'=>'fa-youtube','tiktok'=>'fa-tiktok','behance'=>'fa-behance','dribbble'=>'fa-dribbble','medium'=>'fa-medium','stackoverflow'=>'fa-stack-overflow','website'=>'fa-globe'];

// Visibility helper
function vis(array $vis, string $field, bool $default = false): bool {
    return (bool)($vis[$field] ?? $default);
}

$countryCodes = [
    '+880 BD','+91 IN','+92 PK','+971 AE','+966 SA','+1 US',
    '+44 GB','+61 AU','+49 DE','+33 FR','+81 JP','+86 CN',
    '+65 SG','+60 MY','+62 ID','+55 BR','+7 RU','+27 ZA',
];
?>
<style>
/* ── Profile edit layout — mirrors app settings ───────────────────────────── */
.pe-wrap{display:flex;gap:24px;align-items:flex-start;max-width:980px}
.pe-nav{width:230px;flex-shrink:0;background:var(--card-bg);border:1px solid var(--border);border-radius:12px;overflow:hidden;position:sticky;top:20px}
.pe-nav-header{padding:20px 16px 16px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:12px}
.pe-avatar{width:48px;height:48px;border-radius:50%;object-fit:cover;background:var(--brand);display:flex;align-items:center;justify-content:center;color:#fff;font-size:20px;font-weight:700;flex-shrink:0}
.pe-avatar img{width:100%;height:100%;border-radius:50%;object-fit:cover}
.pe-user-name{font-size:14px;font-weight:700;color:var(--text);overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.pe-user-handle{font-size:12px;color:var(--brand);margin-top:2px}
.pe-nav a{display:flex;align-items:center;gap:10px;padding:11px 16px;color:var(--text);text-decoration:none;font-size:13px;font-weight:500;border-bottom:1px solid var(--border);transition:background .12s}
.pe-nav a:last-child{border-bottom:none}
.pe-nav a:hover{background:var(--hover-bg,rgba(0,0,0,.04))}
.pe-nav a.active{background:var(--brand-light,rgba(26,107,74,.08));color:var(--brand);font-weight:600}
.pe-nav a i{width:18px;text-align:center;font-size:14px}
.pe-nav .nav-group{padding:8px 16px 4px;font-size:10px;font-weight:700;letter-spacing:.06em;color:var(--text-muted);text-transform:uppercase;background:var(--bg)}
.pe-body{flex:1;min-width:0;display:flex;flex-direction:column;gap:0}
.pe-panel{background:var(--card-bg);border:1px solid var(--border);border-radius:12px;padding:28px;margin-bottom:20px}
.pe-panel h2{font-size:17px;font-weight:700;margin:0 0 4px;color:var(--text)}
.pe-panel .panel-desc{font-size:13px;color:var(--text-muted);margin:0 0 22px}
.pe-panel hr{border:none;border-top:1px solid var(--border);margin:20px 0}

/* form elements */
.fg{margin-bottom:16px}
.fg label{display:block;font-size:13px;font-weight:600;color:var(--text);margin-bottom:6px}
.fg label .hint{font-weight:400;color:var(--text-muted);margin-left:6px;font-size:12px}
.fg input[type=text],.fg input[type=email],.fg input[type=url],.fg input[type=number],
.fg input[type=date],.fg input[type=password],.fg select,.fg textarea{width:100%;padding:9px 12px;border:1px solid var(--border);border-radius:8px;background:var(--input-bg,var(--bg));color:var(--text);font-size:14px;box-sizing:border-box;transition:border-color .15s;font-family:inherit}
.fg input:focus,.fg select:focus,.fg textarea:focus{outline:none;border-color:var(--brand);box-shadow:0 0 0 3px rgba(26,107,74,.1)}
.fg textarea{resize:vertical;min-height:80px}
.fg-row{display:grid;grid-template-columns:1fr 1fr;gap:16px}
.fg-row-3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px}
.phone-row{display:flex;gap:8px}
.phone-row select{width:130px;flex-shrink:0}
.phone-row input{flex:1}

/* avatar upload */
.avatar-upload{display:flex;align-items:center;gap:16px;margin-bottom:20px}
.avatar-big{width:80px;height:80px;border-radius:50%;object-fit:cover;background:var(--brand);display:flex;align-items:center;justify-content:center;color:#fff;font-size:32px;font-weight:700;flex-shrink:0;overflow:hidden;border:3px solid var(--border)}
.avatar-big img{width:100%;height:100%;object-fit:cover}
.banner-upload{border:2px dashed var(--border);border-radius:10px;padding:20px;text-align:center;cursor:pointer;transition:all .2s;background:var(--bg);position:relative;overflow:hidden;min-height:100px}
.banner-upload:hover{border-color:var(--brand);background:var(--brand-light,rgba(26,107,74,.05))}
.banner-upload input[type=file]{position:absolute;inset:0;opacity:0;cursor:pointer}
.banner-preview{width:100%;height:120px;object-fit:cover;border-radius:8px;margin-top:10px;display:none}

/* visibility toggles */
.vis-row{display:flex;align-items:center;justify-content:space-between;padding:10px 0;border-bottom:1px solid var(--border)}
.vis-row:last-child{border-bottom:none}
.vis-info strong{display:block;font-size:13px;font-weight:600;color:var(--text)}
.vis-info span{font-size:12px;color:var(--text-muted)}
.toggle-switch{position:relative;width:42px;height:22px;flex-shrink:0}
.toggle-switch input{opacity:0;width:0;height:0;position:absolute}
.toggle-slider{position:absolute;inset:0;background:#ccc;border-radius:22px;cursor:pointer;transition:.2s}
.toggle-slider:before{content:'';position:absolute;height:16px;width:16px;left:3px;bottom:3px;background:#fff;border-radius:50%;transition:.2s}
.toggle-switch input:checked + .toggle-slider{background:var(--brand)}
.toggle-switch input:checked + .toggle-slider:before{transform:translateX(20px)}

/* repeatable rows */
.repeat-item{display:flex;gap:8px;align-items:flex-start;padding:12px;background:var(--bg);border:1px solid var(--border);border-radius:8px;margin-bottom:8px}
.repeat-item .item-fields{flex:1;display:grid;gap:8px}
.repeat-del{background:none;border:none;cursor:pointer;color:var(--text-muted);font-size:18px;padding:4px;border-radius:6px;transition:all .15s;margin-top:2px;flex-shrink:0}
.repeat-del:hover{background:rgba(229,62,62,.1);color:#e53e3e}
.add-row-btn{display:inline-flex;align-items:center;gap:6px;padding:8px 14px;background:var(--bg);border:1px dashed var(--border);border-radius:8px;color:var(--text-muted);font-size:13px;cursor:pointer;transition:all .15s;width:100%;justify-content:center;margin-top:4px}
.add-row-btn:hover{border-color:var(--brand);color:var(--brand)}

/* color swatch */
.color-pick-row{display:flex;align-items:center;gap:12px}
.color-swatch{width:40px;height:40px;border-radius:10px;border:2px solid var(--border);cursor:pointer;position:relative;overflow:hidden;flex-shrink:0}
.color-swatch input[type=color]{position:absolute;inset:0;width:100%;height:100%;opacity:0;cursor:pointer}

/* social platform icon */
.social-platform-row{display:flex;gap:8px;align-items:center;margin-bottom:8px}
.social-platform-icon{width:36px;height:36px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0;background:var(--bg);border:1px solid var(--border)}

/* public profile link card */
.profile-link-card{background:var(--brand-light,rgba(26,107,74,.07));border:1px solid var(--brand);border-radius:10px;padding:14px 18px;display:flex;align-items:center;gap:14px}
.profile-link-card i{font-size:22px;color:var(--brand)}
.profile-link-card .link-text{flex:1}
.profile-link-card strong{display:block;font-size:14px;font-weight:700;color:var(--text)}
.profile-link-card a{font-size:13px;color:var(--brand);word-break:break-all;text-decoration:none}
.profile-link-card a:hover{text-decoration:underline}

/* security panel */
.security-badge{display:inline-flex;align-items:center;gap:6px;padding:4px 10px;border-radius:20px;font-size:12px;font-weight:600}
.badge-on{background:rgba(26,107,74,.12);color:#1a6b4a}
.badge-off{background:rgba(239,68,68,.1);color:#ef4444}
.tfa-method-card{border:2px solid var(--border);border-radius:10px;padding:14px 16px;cursor:pointer;transition:all .2s;display:flex;align-items:center;gap:12px}
.tfa-method-card:hover{border-color:var(--brand)}
.tfa-method-card.selected{border-color:var(--brand);background:var(--brand-light,rgba(26,107,74,.06))}
.tfa-method-card input[type=radio]{display:none}
.tfa-method-card .tmc-icon{width:38px;height:38px;border-radius:10px;background:var(--bg);border:1px solid var(--border);display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0}
.tfa-method-card.selected .tmc-icon{background:var(--brand);border-color:var(--brand);color:#fff}

/* session card */
.session-item{display:flex;align-items:center;gap:12px;padding:12px 0;border-bottom:1px solid var(--border)}
.session-item:last-child{border-bottom:none}
.session-icon{width:36px;height:36px;border-radius:8px;background:var(--bg);border:1px solid var(--border);display:flex;align-items:center;justify-content:center;font-size:15px;color:var(--text-muted);flex-shrink:0}
.session-current{background:rgba(26,107,74,.1);border-color:var(--brand);color:var(--brand)}

@media(max-width:760px){.pe-wrap{flex-direction:column}.pe-nav{width:100%;position:static}.fg-row,.fg-row-3{grid-template-columns:1fr}}
</style>

<?php if (!empty($_SESSION['flash_success'])): ?>
<div class="alert alert-success" style="margin-bottom:16px"><i class="fa-solid fa-check-circle"></i> <?= e($_SESSION['flash_success']) ?><?php unset($_SESSION['flash_success']); ?></div>
<?php endif; ?>
<?php if (!empty($_SESSION['flash_error'])): ?>
<div class="alert alert-error" style="margin-bottom:16px"><i class="fa-solid fa-triangle-exclamation"></i> <?= e($_SESSION['flash_error']) ?><?php unset($_SESSION['flash_error']); ?></div>
<?php endif; ?>

<div class="page-header">
    <div class="page-header-left">
        <div class="breadcrumb"><span>My Profile</span></div>
        <h1><i class="fa-solid fa-id-card" style="color:var(--brand)"></i> My Profile</h1>
        <p>Edit your personal information, CV, and public profile</p>
    </div>
    <?php if ($handle): ?>
    <a href="/user/@<?= e($handle['handle']) ?>" target="_blank" class="btn btn-secondary btn-sm">
        <i class="fa-solid fa-arrow-up-right-from-square"></i> View Public Profile
    </a>
    <?php endif; ?>
</div>

<div class="pe-wrap">
    <!-- Sidebar Nav -->
    <nav class="pe-nav">
        <div class="pe-nav-header">
            <div class="pe-avatar">
                <?php if (!empty($user['avatar'])): ?>
                <img src="<?= asset('uploads/'.$user['avatar']) ?>" alt="">
                <?php else: ?>
                <?= mb_substr($user['name'] ?? 'U', 0, 1) ?>
                <?php endif; ?>
            </div>
            <div>
                <div class="pe-user-name"><?= e($user['name'] ?? '') ?></div>
                <div class="pe-user-handle"><?= $handle ? '@'.e($handle['handle']) : 'No handle yet' ?></div>
            </div>
        </div>
        <div class="nav-group">Personal</div>
        <a href="?tab=basic"      class="<?= $tab==='basic'?'active':'' ?>"><i class="fa-solid fa-user"></i> Basic Info</a>
        <a href="?tab=profile"    class="<?= $tab==='profile'?'active':'' ?>"><i class="fa-solid fa-address-card"></i> Profile Details</a>
        <a href="?tab=education"  class="<?= $tab==='education'?'active':'' ?>"><i class="fa-solid fa-graduation-cap"></i> Education</a>
        <a href="?tab=experience" class="<?= $tab==='experience'?'active':'' ?>"><i class="fa-solid fa-briefcase"></i> Experience</a>
        <a href="?tab=social"     class="<?= $tab==='social'?'active':'' ?>"><i class="fa-solid fa-share-nodes"></i> Social Links</a>
        <div class="nav-group">Privacy & Security</div>
        <a href="?tab=visibility" class="<?= $tab==='visibility'?'active':'' ?>"><i class="fa-solid fa-eye"></i> Visibility</a>
        <a href="?tab=security"   class="<?= $tab==='security'?'active':'' ?>"><i class="fa-solid fa-shield-halved"></i> Security</a>
        <div class="nav-group">CV</div>
        <a href="/profile/cv/pdf" target="_blank"><i class="fa-solid fa-print"></i> Print / Download CV</a>
    </nav>

    <!-- Content -->
    <div class="pe-body">

    <?php if ($tab === 'basic'): ?>
    <!-- ── BASIC INFO ───────────────────────────────────────────────────── -->

    <?php if ($handle): ?>
    <div class="profile-link-card" style="margin-bottom:20px">
        <i class="fa-solid fa-link"></i>
        <div class="link-text">
            <strong>Your Public Profile Link</strong>
            <a href="/user/@<?= e($handle['handle']) ?>" target="_blank">
                https://byabsayee.com/user/@<?= e($handle['handle']) ?>
            </a>
        </div>
        <button onclick="navigator.clipboard.writeText('https://byabsayee.com/user/@<?= e($handle['handle']) ?>');this.textContent='Copied!';setTimeout(()=>this.textContent='Copy',2000)" class="btn btn-sm btn-secondary">Copy</button>
    </div>
    <?php endif; ?>

    <!-- Unique Handle -->
    <div class="pe-panel">
        <h2>Unique Handle</h2>
        <p class="panel-desc">Your @handle is your unique identity on Byabsayee. Once set, it becomes your public profile URL.</p>
        <form method="POST" action="/profile/handle">
            <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
            <div class="fg">
                <label>Your Handle <span class="hint">Letters, numbers, underscores. 3–40 characters.</span></label>
                <div style="display:flex;gap:8px;align-items:center">
                    <span style="font-size:22px;color:var(--brand);font-weight:700;flex-shrink:0">@</span>
                    <input type="text" name="handle" value="<?= e($handle['handle'] ?? '') ?>" placeholder="yourname" pattern="[a-z0-9_]+" minlength="3" maxlength="40" required style="font-size:18px;font-weight:600;letter-spacing:-.3px">
                </div>
                <div style="font-size:12px;color:var(--text-muted);margin-top:6px">
                    Preview: <strong>https://byabsayee.com/user/@<?= e($handle['handle'] ?? 'yourname') ?></strong>
                </div>
            </div>
            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check"></i> Save Handle</button>
        </form>
    </div>

    <!-- Basic Info -->
    <div class="pe-panel">
        <h2>Basic Information</h2>
        <p class="panel-desc">Your name, contact details, and avatar.</p>
        <form method="POST" action="/profile/basic" enctype="multipart/form-data">
            <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">

            <!-- Avatar -->
            <div class="avatar-upload">
                <div class="avatar-big">
                    <?php if (!empty($user['avatar'])): ?>
                    <img src="<?= asset('uploads/'.$user['avatar']) ?>" alt="" id="avatarPreviewImg">
                    <?php else: ?>
                    <span id="avatarInitial"><?= mb_substr($user['name']??'U',0,1) ?></span>
                    <img id="avatarPreviewImg" style="display:none;width:100%;height:100%;object-fit:cover">
                    <?php endif; ?>
                </div>
                <div>
                    <label for="avatarFile" class="btn btn-secondary btn-sm" style="cursor:pointer">
                        <i class="fa-solid fa-camera"></i> Change Photo
                    </label>
                    <input type="file" id="avatarFile" name="avatar" accept="image/*" style="display:none"
                           onchange="previewAvatar(this)">
                    <div style="font-size:12px;color:var(--text-muted);margin-top:6px">JPG, PNG, WEBP · Max 5MB</div>
                </div>
            </div>

            <div class="fg-row">
                <div class="fg">
                    <label>Full Name *</label>
                    <input type="text" name="name" value="<?= e($user['name']??'') ?>" required>
                </div>
                <div class="fg">
                    <label>Email Address *</label>
                    <input type="email" name="email" value="<?= e($user['email']??'') ?>" required>
                </div>
            </div>

            <div class="fg-row">
                <div class="fg">
                    <label>Phone Number</label>
                    <div class="phone-row">
                        <select name="phone_country_code">
                            <?php foreach ($countryCodes as $cc): [$c,$n] = explode(' ',$cc,2);
                                $sel = ($user['phone_country_code']??'+880') === $c ? 'selected' : ''; ?>
                            <option value="<?= e($c) ?>" <?= $sel ?>><?= e($c) ?> <?= e($n) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <input type="text" name="phone" value="<?= e($user['phone']??'') ?>" placeholder="01XXXXXXXXX">
                    </div>
                </div>
                <div class="fg">
                    <label>WhatsApp Number</label>
                    <div class="phone-row">
                        <select name="whatsapp_country_code">
                            <?php foreach ($countryCodes as $cc): [$c,$n] = explode(' ',$cc,2);
                                $sel = ($user['whatsapp_country_code']??'+880') === $c ? 'selected' : ''; ?>
                            <option value="<?= e($c) ?>" <?= $sel ?>><?= e($c) ?> <?= e($n) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <input type="text" name="whatsapp_number" value="<?= e($user['whatsapp_number']??'') ?>" placeholder="01XXXXXXXXX">
                    </div>
                </div>
            </div>

            <div class="fg-row-3">
                <div class="fg">
                    <label>Date of Birth</label>
                    <input type="date" name="date_of_birth" value="<?= e($user['date_of_birth']??'') ?>">
                </div>
                <div class="fg">
                    <label>Gender</label>
                    <select name="gender">
                        <option value="">— Select —</option>
                        <?php foreach (['male'=>'Male','female'=>'Female','other'=>'Other','prefer_not'=>'Prefer not to say'] as $v=>$l): ?>
                        <option value="<?=$v?>" <?= ($user['gender']??'')===$v?'selected':'' ?>><?= $l ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="fg">
                    <label>Blood Group</label>
                    <select name="blood_group">
                        <option value="">— Select —</option>
                        <?php foreach (['A+','A-','B+','B-','AB+','AB-','O+','O-'] as $bg): ?>
                        <option value="<?=$bg?>" <?= ($user['blood_group']??'')===$bg?'selected':'' ?>><?=$bg?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Save Basic Info</button>
        </form>
    </div>

    <?php elseif ($tab === 'profile'): ?>
    <!-- ── PROFILE DETAILS ─────────────────────────────────────────────── -->
    <div class="pe-panel">
        <h2>Profile Details</h2>
        <p class="panel-desc">Your bio, address, headline, business association, and profile appearance.</p>
        <form method="POST" action="/profile/profile" enctype="multipart/form-data">
            <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">

            <!-- Profile Banner -->
            <div class="fg">
                <label>Profile Banner <span class="hint">Wide background image behind your profile. Recommended 1200×300px</span></label>
                <div class="banner-upload" onclick="document.getElementById('bannerFile').click()">
                    <i class="fa-solid fa-image" style="font-size:24px;color:var(--text-muted)"></i>
                    <div style="font-size:13px;color:var(--text-muted);margin-top:6px">Click to upload banner · JPG, PNG, WEBP · Max 5MB</div>
                    <input type="file" id="bannerFile" name="profile_banner" accept="image/*" style="display:none"
                           onchange="previewBanner(this)">
                    <?php if (!empty($profile['profile_banner'])): ?>
                    <img src="<?= asset('uploads/'.$profile['profile_banner']) ?>" class="banner-preview" id="bannerPreview" style="display:block">
                    <?php else: ?>
                    <img class="banner-preview" id="bannerPreview">
                    <?php endif; ?>
                </div>
            </div>

            <div class="fg">
                <label>CV Headline <span class="hint">e.g. "Full Stack Developer | 5 years experience"</span></label>
                <input type="text" name="profile_cv_headline" value="<?= e($profile['profile_cv_headline']??'') ?>" placeholder="Your professional title or tagline">
            </div>

            <div class="fg">
                <label>Bio</label>
                <textarea name="bio" rows="4" placeholder="Tell the world about yourself..."><?= e($profile['bio']??'') ?></textarea>
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
                    <label>Relationship Status</label>
                    <select name="relationship_status">
                        <option value="">— Select —</option>
                        <?php foreach (['single'=>'Single','in_relationship'=>'In a Relationship','married'=>'Married','widowed'=>'Widowed','prefer_not'=>'Prefer not to say'] as $v=>$l): ?>
                        <option value="<?=$v?>" <?= ($profile['relationship_status']??'')===$v?'selected':'' ?>><?=$l?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <hr>
            <h3 style="font-size:15px;font-weight:700;margin:0 0 16px">Professional Info</h3>

            <div class="fg">
                <label>Expertise / Skills <span class="hint">Comma-separated, e.g. PHP, MySQL, Business, Sales</span></label>
                <input type="text" name="expertise" value="<?= e($profile['expertise']??'') ?>" placeholder="PHP, MySQL, Accounting...">
            </div>

            <div class="fg">
                <label>Languages <span class="hint">Comma-separated, e.g. English, Bengali, Arabic</span></label>
                <input type="text" name="languages" value="<?= e($profile['languages']??'') ?>" placeholder="English, Bengali...">
            </div>

            <div class="fg">
                <label>Interests &amp; Hobbies <span class="hint">Comma-separated, e.g. Photography, Travelling, Chess</span></label>
                <input type="text" name="hobbies" value="<?= e($profile['hobbies']??'') ?>" placeholder="Photography, Reading, Chess...">
            </div>

            <div class="fg-row">
                <div class="fg">
                    <label>Years of Experience</label>
                    <input type="number" name="experience_years" value="<?= e($profile['experience_years']??'') ?>" min="0" max="60" placeholder="e.g. 5">
                </div>
                <div class="fg">
                    <label>Website / Portfolio</label>
                    <input type="url" name="website" value="<?= e($profile['website']??'') ?>" placeholder="https://yourwebsite.com">
                </div>
            </div>

            <?php if (!empty($myBooks)): ?>
            <hr>
            <h3 style="font-size:15px;font-weight:700;margin:0 0 16px">Business Association</h3>
            <p style="font-size:13px;color:var(--text-muted);margin:0 0 14px">Select which business to show on your public profile. Your designation and joining date will auto-fill.</p>
            <div class="fg-row">
                <div class="fg">
                    <label>Primary Business</label>
                    <select name="selected_book_id" id="bizSelect" onchange="updateDesignation(this)">
                        <option value="">— None —</option>
                        <?php foreach ($myBooks as $b): ?>
                        <option value="<?= $b['id'] ?>"
                                data-designation="<?= e($b['designation']??'') ?>"
                                <?= ($profile['selected_book_id']??0)==$b['id']?'selected':'' ?>>
                            <?= e($b['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="fg">
                    <label>Designation <span class="hint">Auto-filled from selected business</span></label>
                    <input type="text" name="designation" id="designationInput" value="<?= e($profile['designation']??'') ?>" placeholder="e.g. Manager, Developer">
                </div>
            </div>
            <div class="fg">
                <label>Working Since</label>
                <input type="date" name="working_since" value="<?= e($profile['working_since']??'') ?>">
            </div>
            <?php endif; ?>

            <hr>
            <div class="fg-row">
                <div class="fg">
                    <label>Public Email <span class="hint">Shown on profile instead of login email</span></label>
                    <input type="email" name="public_email" value="<?= e($profile['public_email']??'') ?>" placeholder="contact@example.com">
                </div>
                <div class="fg">
                    <label>Public Phone</label>
                    <input type="text" name="public_phone" value="<?= e($profile['public_phone']??'') ?>" placeholder="+880 1XXXXXXXXX">
                </div>
            </div>

            <div class="fg">
                <label>Profile Theme Color</label>
                <div class="color-pick-row">
                    <div class="color-swatch" style="background:<?= e($themeColor) ?>">
                        <input type="color" name="profile_theme_color" value="<?= e($themeColor) ?>"
                               oninput="this.parentElement.style.background=this.value">
                    </div>
                    <span style="font-size:13px;color:var(--text-muted)">Used as accent on your public profile</span>
                </div>
            </div>

            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Save Profile Details</button>
        </form>
    </div>

    <?php elseif ($tab === 'education'): ?>
    <!-- ── EDUCATION ───────────────────────────────────────────────────── -->
    <div class="pe-panel">
        <h2>Education</h2>
        <p class="panel-desc">Add your educational background. Multiple institutions supported.</p>
        <form method="POST" action="/profile/education">
            <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">

            <h3 style="font-size:14px;font-weight:700;margin:0 0 12px">Institutions</h3>
            <div id="eduList">
                <?php foreach ($education as $i => $edu): ?>
                <div class="repeat-item">
                    <div class="item-fields" style="grid-template-columns:1fr 1fr">
                        <div class="fg" style="margin:0"><label style="font-size:11px">Institute Name *</label>
                            <input type="text" name="institute[]" value="<?= e($edu['institute']) ?>" required placeholder="e.g. Dhaka University"></div>
                        <div class="fg" style="margin:0"><label style="font-size:11px">Subject / Department</label>
                            <input type="text" name="subject[]" value="<?= e($edu['subject']??'') ?>" placeholder="e.g. Computer Science"></div>
                        <div class="fg" style="margin:0"><label style="font-size:11px">From Year</label>
                            <input type="number" name="from_year[]" value="<?= e($edu['from_year']??'') ?>" min="1950" max="2050" placeholder="2018"></div>
                        <div class="fg" style="margin:0"><label style="font-size:11px">To Year</label>
                            <input type="number" name="to_year[]" value="<?= e($edu['to_year']??'') ?>" min="1950" max="2050" placeholder="2022"></div>
                        <div style="grid-column:span 2">
                            <label style="display:flex;align-items:center;gap:6px;font-size:12px;cursor:pointer;font-weight:500">
                                <input type="checkbox" name="is_current[]" value="1" <?= $edu['is_current']?'checked':'' ?>>
                                Currently studying here
                            </label>
                        </div>
                    </div>
                    <button type="button" class="repeat-del" onclick="this.closest('.repeat-item').remove()"><i class="fa-solid fa-xmark"></i></button>
                </div>
                <?php endforeach; ?>
                <?php if (empty($education)): ?>
                <div class="repeat-item">
                    <div class="item-fields" style="grid-template-columns:1fr 1fr">
                        <div class="fg" style="margin:0"><label style="font-size:11px">Institute Name *</label>
                            <input type="text" name="institute[]" placeholder="e.g. Dhaka University"></div>
                        <div class="fg" style="margin:0"><label style="font-size:11px">Subject / Department</label>
                            <input type="text" name="subject[]" placeholder="e.g. Computer Science"></div>
                        <div class="fg" style="margin:0"><label style="font-size:11px">From Year</label>
                            <input type="number" name="from_year[]" min="1950" max="2050" placeholder="2018"></div>
                        <div class="fg" style="margin:0"><label style="font-size:11px">To Year</label>
                            <input type="number" name="to_year[]" min="1950" max="2050" placeholder="2022"></div>
                    </div>
                    <button type="button" class="repeat-del" onclick="this.closest('.repeat-item').remove()"><i class="fa-solid fa-xmark"></i></button>
                </div>
                <?php endif; ?>
            </div>
            <button type="button" class="add-row-btn" onclick="addEduRow()"><i class="fa-solid fa-plus"></i> Add Institution</button>

            <hr>
            <h3 style="font-size:14px;font-weight:700;margin:16px 0 12px">Grades / Qualifications</h3>
            <p style="font-size:12px;color:var(--text-muted);margin:0 0 12px">Add your exam results (SSC, HSC, Bachelor's etc.)</p>
            <div id="gradeList">
                <?php foreach ($grades as $g): ?>
                <div class="repeat-item">
                    <div class="item-fields" style="grid-template-columns:1fr 1fr 1fr 1fr">
                        <div class="fg" style="margin:0"><label style="font-size:11px">Level *</label>
                            <input type="text" name="grade_level[]" value="<?= e($g['level']) ?>" placeholder="SSC / HSC / BSc"></div>
                        <div class="fg" style="margin:0"><label style="font-size:11px">Result / GPA</label>
                            <input type="text" name="grade_result[]" value="<?= e($g['result']??'') ?>" placeholder="GPA 5.00"></div>
                        <div class="fg" style="margin:0"><label style="font-size:11px">Board / University</label>
                            <input type="text" name="grade_board[]" value="<?= e($g['board']??'') ?>" placeholder="Dhaka Board"></div>
                        <div class="fg" style="margin:0"><label style="font-size:11px">Year</label>
                            <input type="number" name="grade_year[]" value="<?= e($g['year']??'') ?>" min="1990" max="2050" placeholder="2020"></div>
                    </div>
                    <button type="button" class="repeat-del" onclick="this.closest('.repeat-item').remove()"><i class="fa-solid fa-xmark"></i></button>
                </div>
                <?php endforeach; ?>
                <?php if (empty($grades)): ?>
                <div class="repeat-item">
                    <div class="item-fields" style="grid-template-columns:1fr 1fr 1fr 1fr">
                        <div class="fg" style="margin:0"><label style="font-size:11px">Level *</label>
                            <input type="text" name="grade_level[]" placeholder="SSC / HSC / BSc"></div>
                        <div class="fg" style="margin:0"><label style="font-size:11px">Result / GPA</label>
                            <input type="text" name="grade_result[]" placeholder="GPA 5.00"></div>
                        <div class="fg" style="margin:0"><label style="font-size:11px">Board / University</label>
                            <input type="text" name="grade_board[]" placeholder="Dhaka Board"></div>
                        <div class="fg" style="margin:0"><label style="font-size:11px">Year</label>
                            <input type="number" name="grade_year[]" min="1990" max="2050" placeholder="2020"></div>
                    </div>
                    <button type="button" class="repeat-del" onclick="this.closest('.repeat-item').remove()"><i class="fa-solid fa-xmark"></i></button>
                </div>
                <?php endif; ?>
            </div>
            <button type="button" class="add-row-btn" onclick="addGradeRow()"><i class="fa-solid fa-plus"></i> Add Grade</button>

            <div style="margin-top:20px">
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Save Education</button>
            </div>
        </form>
    </div>

    <?php elseif ($tab === 'experience'): ?>
    <!-- ── WORK EXPERIENCE ─────────────────────────────────────────────── -->
    <div class="pe-panel">
        <h2><i class="fa-solid fa-briefcase" style="color:var(--brand);margin-right:8px"></i>Work Experience</h2>
        <p class="panel-desc">Add your professional work history. Each entry can be toggled visible on your public profile via the Visibility tab.</p>
        <form method="POST" action="/profile/experience">
            <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
            <div id="expList">
                <?php if (!empty($experience)): foreach ($experience as $exp): ?>
                <div class="repeat-item">
                    <div class="item-fields" style="grid-template-columns:1fr 1fr">
                        <div class="fg" style="margin:0"><label style="font-size:11px">Organisation / Company *</label>
                            <input type="text" name="exp_org[]" value="<?= e($exp['organisation']) ?>" required placeholder="e.g. Acme Corporation"></div>
                        <div class="fg" style="margin:0"><label style="font-size:11px">Job Title / Designation *</label>
                            <input type="text" name="exp_title[]" value="<?= e($exp['job_title']) ?>" required placeholder="e.g. Senior Developer"></div>
                        <div class="fg" style="margin:0"><label style="font-size:11px">Employment Type</label>
                            <select name="exp_type[]">
                                <?php foreach (['full_time'=>'Full-time','part_time'=>'Part-time','contract'=>'Contract','freelance'=>'Freelance','internship'=>'Internship','volunteer'=>'Volunteer'] as $v=>$l): ?>
                                <option value="<?=$v?>" <?= ($exp['employment_type']??'')===$v?'selected':'' ?>><?=$l?></option>
                                <?php endforeach; ?>
                            </select></div>
                        <div class="fg" style="margin:0"><label style="font-size:11px">Location</label>
                            <input type="text" name="exp_location[]" value="<?= e($exp['location']??'') ?>" placeholder="e.g. Dhaka, Bangladesh"></div>
                        <div class="fg" style="margin:0"><label style="font-size:11px">Start Date</label>
                            <input type="date" name="exp_start[]" value="<?= e($exp['start_date']??'') ?>"></div>
                        <div class="fg" style="margin:0"><label style="font-size:11px">End Date</label>
                            <input type="date" name="exp_end[]" value="<?= e($exp['end_date']??'') ?>" <?= $exp['is_current']?'disabled':'' ?>></div>
                        <div style="grid-column:span 2">
                            <label style="display:flex;align-items:center;gap:6px;font-size:12px;cursor:pointer;font-weight:500">
                                <input type="checkbox" name="exp_current[]" value="1" <?= $exp['is_current']?'checked':'' ?>
                                       onchange="toggleEndDate(this)">
                                I currently work here
                            </label>
                        </div>
                        <div class="fg" style="grid-column:span 2;margin:0"><label style="font-size:11px">Description / Responsibilities</label>
                            <textarea name="exp_desc[]" rows="2" placeholder="Brief description of your role and achievements..."><?= e($exp['description']??'') ?></textarea></div>
                    </div>
                    <button type="button" class="repeat-del" onclick="this.closest('.repeat-item').remove()"><i class="fa-solid fa-xmark"></i></button>
                </div>
                <?php endforeach; else: ?>
                <div class="repeat-item" id="expRow0">
                    <div class="item-fields" style="grid-template-columns:1fr 1fr">
                        <div class="fg" style="margin:0"><label style="font-size:11px">Organisation / Company *</label>
                            <input type="text" name="exp_org[]" required placeholder="e.g. Acme Corporation"></div>
                        <div class="fg" style="margin:0"><label style="font-size:11px">Job Title / Designation *</label>
                            <input type="text" name="exp_title[]" required placeholder="e.g. Senior Developer"></div>
                        <div class="fg" style="margin:0"><label style="font-size:11px">Employment Type</label>
                            <select name="exp_type[]">
                                <option value="full_time">Full-time</option>
                                <option value="part_time">Part-time</option>
                                <option value="contract">Contract</option>
                                <option value="freelance">Freelance</option>
                                <option value="internship">Internship</option>
                                <option value="volunteer">Volunteer</option>
                            </select></div>
                        <div class="fg" style="margin:0"><label style="font-size:11px">Location</label>
                            <input type="text" name="exp_location[]" placeholder="e.g. Dhaka, Bangladesh"></div>
                        <div class="fg" style="margin:0"><label style="font-size:11px">Start Date</label>
                            <input type="date" name="exp_start[]"></div>
                        <div class="fg" style="margin:0"><label style="font-size:11px">End Date</label>
                            <input type="date" name="exp_end[]"></div>
                        <div style="grid-column:span 2">
                            <label style="display:flex;align-items:center;gap:6px;font-size:12px;cursor:pointer;font-weight:500">
                                <input type="checkbox" name="exp_current[]" value="1" onchange="toggleEndDate(this)">
                                I currently work here
                            </label>
                        </div>
                        <div class="fg" style="grid-column:span 2;margin:0"><label style="font-size:11px">Description / Responsibilities</label>
                            <textarea name="exp_desc[]" rows="2" placeholder="Brief description of your role and achievements..."></textarea></div>
                    </div>
                    <button type="button" class="repeat-del" onclick="this.closest('.repeat-item').remove()"><i class="fa-solid fa-xmark"></i></button>
                </div>
                <?php endif; ?>
            </div>
            <button type="button" class="add-row-btn" onclick="addExpRow()"><i class="fa-solid fa-plus"></i> Add Experience</button>
            <div style="margin-top:20px">
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Save Experience</button>
            </div>
        </form>
    </div>

    <?php elseif ($tab === 'social'): ?>
    <!-- ── SOCIAL LINKS ────────────────────────────────────────────────── -->
    <div class="pe-panel">
        <h2>Social Links</h2>
        <p class="panel-desc">Add your social media profiles and other online presence.</p>
        <form method="POST" action="/profile/social">
            <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
            <div id="socialList">
                <?php
                if (!empty($socialLinks)):
                    foreach ($socialLinks as $sl): ?>
                <div class="social-platform-row">
                    <div class="social-platform-icon">
                        <i class="fa-brands <?= e($platformIcons[$sl['platform']] ?? 'fa-link') ?>"></i>
                    </div>
                    <select name="platform[]" style="width:140px" onchange="updateSocialIcon(this)">
                        <?php foreach ($platforms as $p): ?>
                        <option value="<?=$p?>" <?= $sl['platform']===$p?'selected':'' ?>><?= ucfirst($p) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="url" name="social_url[]" value="<?= e($sl['url']) ?>" placeholder="https://..." style="flex:1">
                    <button type="button" onclick="this.closest('.social-platform-row').remove()" class="repeat-del"><i class="fa-solid fa-xmark"></i></button>
                </div>
                <?php endforeach; ?>
                <?php else: ?>
                <div class="social-platform-row">
                    <div class="social-platform-icon"><i class="fa-brands fa-linkedin"></i></div>
                    <select name="platform[]" style="width:140px" onchange="updateSocialIcon(this)">
                        <?php foreach ($platforms as $p): ?>
                        <option value="<?=$p?>"><?= ucfirst($p) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="url" name="social_url[]" placeholder="https://linkedin.com/in/yourname" style="flex:1">
                    <button type="button" onclick="this.closest('.social-platform-row').remove()" class="repeat-del"><i class="fa-solid fa-xmark"></i></button>
                </div>
                <?php endif; ?>
            </div>
            <button type="button" class="add-row-btn" style="margin-top:8px" onclick="addSocialRow()"><i class="fa-solid fa-plus"></i> Add Link</button>
            <div style="margin-top:20px">
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Save Social Links</button>
            </div>
        </form>
    </div>

    <?php elseif ($tab === 'visibility'): ?>
    <!-- ── VISIBILITY ──────────────────────────────────────────────────── -->
    <div class="pe-panel">
        <h2><i class="fa-solid fa-eye"></i> Public Visibility</h2>
        <p class="panel-desc">Choose what information is visible to anyone who visits your public profile link. Unchecked fields remain private.</p>
        <form method="POST" action="/profile/visibility">
            <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
            <?php
            $visFields = [
                'name'              => ['Full Name', 'Your displayed name'],
                'email'             => ['Email Address', 'Public contact email'],
                'phone'             => ['Phone Number', 'Your phone number'],
                'whatsapp_number'   => ['WhatsApp Number', 'WhatsApp contact'],
                'date_of_birth'     => ['Date of Birth', 'Your birthday'],
                'gender'            => ['Gender', 'Gender identity'],
                'blood_group'       => ['Blood Group', 'Blood type'],
                'address'           => ['Address / Location', 'City and country'],
                'bio'               => ['Bio', 'About me section'],
                'headline'          => ['CV Headline', 'Professional headline'],
                'education'         => ['Education', 'Institutions and degrees'],
                'grades'            => ['Grades / Results', 'Academic results'],
                'experience'        => ['Work Experience', 'Job history and organisations'],
                'social_links'      => ['Social Links', 'LinkedIn, GitHub, etc.'],
                'relationship_status'=>['Relationship Status', 'Marital/relationship info'],
                'expertise'         => ['Expertise / Skills', 'Skills and competencies'],
                'languages'         => ['Languages', 'Languages you speak'],
                'hobbies'           => ['Interests & Hobbies', 'Personal interests'],
                'experience_years'  => ['Years of Experience', 'Total experience'],
                'business'          => ['Business Association', 'Company / workplace'],
                'designation'       => ['Designation', 'Job title / role'],
                'working_since'     => ['Working Since', 'Date joined business'],
                'website'           => ['Website / Portfolio', 'Personal website link'],
            ];
            foreach ($visFields as $field => [$label, $desc]): ?>
            <div class="vis-row">
                <div class="vis-info">
                    <strong><?= $label ?></strong>
                    <span><?= $desc ?></span>
                </div>
                <label class="toggle-switch">
                    <input type="checkbox" name="visible_<?= $field ?>" value="1" <?= vis($visRaw,$field)?'checked':'' ?>>
                    <span class="toggle-slider"></span>
                </label>
            </div>
            <?php endforeach; ?>
            <div style="margin-top:20px">
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Save Visibility</button>
            </div>
        </form>
    </div>

    <?php elseif ($tab === 'security'): ?>
    <!-- ── SECURITY ────────────────────────────────────────────────────── -->
    <?php
    $tfa2Enabled   = (bool)($user['two_fa_enabled'] ?? false);
    $pwd2faMode    = isset($_GET['pwd_2fa']);
    $verifyMethod  = $_GET['verify_method'] ?? null; // method being added
    $setupTotp     = isset($_GET['setup_totp']);      // dedicated TOTP setup panel
    $methodDefs = [
        'email'    => ['label'=>'Email OTP',         'icon'=>'fa-envelope',            'desc'=>'A code sent to your email address'],
        'whatsapp' => ['label'=>'WhatsApp OTP',       'icon'=>'fa-brands fa-whatsapp',  'desc'=>'A code sent to your WhatsApp number'],
        'app'      => ['label'=>'Authenticator App',  'icon'=>'fa-mobile-screen-button','desc'=>'Google/Microsoft Authenticator'],
    ];
    ?>

    <!-- Password confirmation notice -->
    <div style="background:rgba(26,107,74,.06);border:1px solid rgba(26,107,74,.2);border-radius:10px;padding:12px 16px;margin-bottom:20px;display:flex;align-items:center;gap:10px;font-size:13px;color:var(--text)">
        <i class="fa-solid fa-circle-info" style="color:var(--brand);font-size:16px;flex-shrink:0"></i>
        <span>All security changes require your <strong>current password</strong> to confirm. Some actions also require a 2FA code if enabled.</span>
    </div>

    <!-- Change Password -->
    <div class="pe-panel">
        <h2><i class="fa-solid fa-lock" style="color:var(--brand);margin-right:8px"></i>Change Password</h2>
        <p class="panel-desc">Keep your account safe with a strong, unique password.</p>

        <?php if ($pwd2faMode): ?>
        <!-- Step 2: Confirm with 2FA code -->
        <div style="background:rgba(26,107,74,.06);border:1px solid rgba(26,107,74,.2);border-radius:10px;padding:14px 16px;margin-bottom:16px;font-size:13px">
            <strong>Password verified.</strong> Enter your 2FA code below to complete the change.
        </div>
        <form method="POST" action="/profile/change-password/2fa">
            <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
            <div class="fg">
                <label>2FA Code <span class="hint">From your authenticator app or email/WhatsApp</span></label>
                <input type="text" name="tfa_code" inputmode="numeric" maxlength="6" placeholder="000000" autofocus style="font-size:20px;font-weight:700;letter-spacing:6px;text-align:center">
            </div>
            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-shield-check"></i> Confirm Password Change</button>
        </form>
        <?php else: ?>
        <form method="POST" action="/profile/change-password">
            <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
            <div class="fg">
                <label>Current Password</label>
                <div style="position:relative">
                    <input type="password" name="current_password" id="pwdCurrent" required placeholder="Enter your current password" style="padding-right:42px">
                    <button type="button" onclick="togglePwd('pwdCurrent',this)" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--text-muted);font-size:14px"><i class="fa-solid fa-eye"></i></button>
                </div>
            </div>
            <div class="fg-row">
                <div class="fg">
                    <label>New Password <span class="hint">Min 8 characters</span></label>
                    <div style="position:relative">
                        <input type="password" name="new_password" id="pwdNew" required placeholder="New password" minlength="8" oninput="checkPwdStrength(this.value)" style="padding-right:42px">
                        <button type="button" onclick="togglePwd('pwdNew',this)" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--text-muted);font-size:14px"><i class="fa-solid fa-eye"></i></button>
                    </div>
                    <div id="pwdStrengthBar" style="height:4px;border-radius:2px;margin-top:6px;background:#eee;overflow:hidden">
                        <div id="pwdStrengthFill" style="height:100%;width:0;transition:width .3s,background .3s;border-radius:2px"></div>
                    </div>
                    <div id="pwdStrengthLabel" style="font-size:11px;color:var(--text-muted);margin-top:3px"></div>
                </div>
                <div class="fg">
                    <label>Confirm New Password</label>
                    <div style="position:relative">
                        <input type="password" name="confirm_password" id="pwdConfirm" required placeholder="Repeat new password" style="padding-right:42px">
                        <button type="button" onclick="togglePwd('pwdConfirm',this)" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--text-muted);font-size:14px"><i class="fa-solid fa-eye"></i></button>
                    </div>
                </div>
            </div>
            <?php if ($tfa2Enabled): ?>
            <div style="background:var(--bg);border:1px solid var(--border);border-radius:8px;padding:12px 14px;margin-bottom:14px;font-size:12px;color:var(--text-muted)">
                <i class="fa-solid fa-shield-halved" style="color:var(--brand)"></i>
                Since 2FA is enabled, you'll be asked to enter a 2FA code after submitting.
            </div>
            <?php endif; ?>
            <div style="background:var(--bg);border:1px solid var(--border);border-radius:8px;padding:12px 14px;margin-bottom:16px;font-size:12px;color:var(--text-muted)">
                <strong style="color:var(--text)">Tips:</strong> Use 12+ characters · Mix uppercase, lowercase, numbers, symbols
            </div>
            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-key"></i> Update Password</button>
        </form>
        <?php endif; ?>
    </div>

    <!-- Two-Factor Authentication -->
    <div class="pe-panel">
        <h2><i class="fa-solid fa-shield-halved" style="color:var(--brand);margin-right:8px"></i>Two-Factor Authentication</h2>
        <p class="panel-desc">You can enable multiple 2FA methods. During login, you'll choose which one to use.</p>

        <!-- Status overview -->
        <div style="display:flex;align-items:center;gap:12px;padding:12px 16px;background:var(--bg);border:1px solid var(--border);border-radius:10px;margin-bottom:20px">
            <i class="fa-solid <?= $tfa2Enabled ? 'fa-shield-check' : 'fa-shield-xmark' ?>" style="font-size:22px;color:<?= $tfa2Enabled ? 'var(--brand)' : '#ef4444' ?>"></i>
            <div style="flex:1">
                <strong style="font-size:14px;color:var(--text)">2FA is <?= $tfa2Enabled ? 'Enabled' : 'Disabled' ?></strong>
                <?php if ($tfa2Enabled && !empty($activeMethods)): ?>
                <div style="font-size:12px;color:var(--text-muted);margin-top:2px">
                    Active methods: <?= implode(', ', array_map(fn($m) => $methodDefs[$m]['label'] ?? ucfirst($m), array_keys($activeMethods))) ?>
                </div>
                <?php endif; ?>
            </div>
            <span class="security-badge <?= $tfa2Enabled ? 'badge-on' : 'badge-off' ?>"><?= $tfa2Enabled ? '✓ Active' : '✗ Off' ?></span>
        </div>

        <!-- Method cards: show add/remove per method -->
        <h3 style="font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--text-muted);margin-bottom:12px">2FA Methods</h3>
        <div style="display:flex;flex-direction:column;gap:10px;margin-bottom:20px">
        <?php foreach ($methodDefs as $mKey => $mDef):
            $isEnabled = isset($activeMethods[$mKey]);
        ?>
        <div style="border:1.5px solid <?= $isEnabled ? 'var(--brand)' : 'var(--border)' ?>;border-radius:10px;padding:14px 16px;background:<?= $isEnabled ? 'rgba(26,107,74,.04)' : 'var(--bg)' ?>">
            <div style="display:flex;align-items:center;gap:12px">
                <div style="width:38px;height:38px;border-radius:9px;background:<?= $isEnabled ? 'var(--brand)' : 'var(--card-bg)' ?>;border:1px solid <?= $isEnabled ? 'var(--brand)' : 'var(--border)' ?>;display:flex;align-items:center;justify-content:center;font-size:16px;color:<?= $isEnabled ? '#fff' : 'var(--text-muted)' ?>;flex-shrink:0">
                    <i class="fa-solid <?= $mDef['icon'] ?>"></i>
                </div>
                <div style="flex:1">
                    <div style="font-size:13px;font-weight:700;color:var(--text)"><?= $mDef['label'] ?>
                        <?php if ($isEnabled): ?><span class="security-badge badge-on" style="margin-left:6px">✓ Active</span><?php endif; ?>
                    </div>
                    <div style="font-size:12px;color:var(--text-muted);margin-top:1px"><?= $mDef['desc'] ?></div>
                </div>
                <?php if ($isEnabled): ?>
                <button type="button" class="btn btn-secondary btn-sm" onclick="openRemoveModal('<?= $mKey ?>', '<?= e($mDef['label']) ?>')">
                    <i class="fa-solid fa-xmark"></i> Remove
                </button>
                <?php if ($mKey === 'app'): ?>
                <a href="?tab=security&setup_totp=1" class="btn btn-secondary btn-sm" style="margin-left:4px;text-decoration:none">
                    <i class="fa-solid fa-qrcode"></i> View QR
                </a>
                <?php endif; ?>
                <?php else: ?>
                <?php if ($mKey === 'app'): ?>
                <a href="?tab=security&setup_totp=1" class="btn btn-primary btn-sm" style="text-decoration:none">
                    <i class="fa-solid fa-qrcode"></i> Set Up
                </a>
                <?php else: ?>
                <button type="button" class="btn btn-primary btn-sm" onclick="openAddModal('<?= $mKey ?>', '<?= e($mDef['label']) ?>')">
                    <i class="fa-solid fa-plus"></i> Enable
                </button>
                <?php endif; ?>
                <?php endif; ?>
            </div>

            <?php if ($verifyMethod === $mKey && $mKey !== 'app'): ?>
            <!-- OTP verification form for email/whatsapp enable -->
            <div style="margin-top:14px;padding-top:14px;border-top:1px solid var(--border)">
                <div style="font-size:13px;color:var(--text-muted);margin-bottom:10px">
                    <i class="fa-solid fa-circle-info" style="color:var(--brand)"></i>
                    A code was sent to your <?= $mKey === 'email' ? 'email' : 'WhatsApp' ?>. Enter it below to enable this method.
                </div>
                <form method="POST" action="/profile/security/2fa" style="display:flex;gap:8px;align-items:flex-end">
                    <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                    <input type="hidden" name="action" value="add_method">
                    <input type="hidden" name="two_fa_method" value="<?= e($mKey) ?>">
                    <input type="hidden" name="confirm_password" value="<?= e($_GET['confirm_password'] ?? '') ?>">
                    <div class="fg" style="margin:0;flex:1">
                        <label style="font-size:12px">Enter OTP Code</label>
                        <input type="text" name="otp_code" inputmode="numeric" maxlength="6" placeholder="000000" autofocus style="font-size:18px;font-weight:700;letter-spacing:4px;text-align:center">
                    </div>
                    <button type="submit" class="btn btn-primary" style="white-space:nowrap"><i class="fa-solid fa-check"></i> Verify &amp; Enable</button>
                </form>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
        </div>

        <!-- ── Dedicated TOTP Setup Panel ─────────────────────────────────── -->
        <?php if ($setupTotp): ?>
        <div class="pe-panel" style="border-color:var(--brand);background:rgba(26,107,74,.03)" id="totpSetupPanel">
            <h2><i class="fa-solid fa-qrcode" style="color:var(--brand);margin-right:8px"></i>Set Up Authenticator App</h2>
            <p class="panel-desc">Use Google Authenticator, Microsoft Authenticator, Authy, or any TOTP-compatible app.</p>

            <div style="display:flex;gap:24px;align-items:flex-start;flex-wrap:wrap;margin-bottom:20px">
                <!-- QR Code -->
                <div style="text-align:center;flex-shrink:0">
                    <div id="qrSpinner" style="width:180px;height:180px;border:2px dashed var(--border);border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:13px;color:var(--text-muted)">Loading…</div>
                    <img id="totpQrFull" src="" alt="QR Code" style="display:none;width:180px;height:180px;border-radius:12px;border:2px solid var(--brand)">
                    <div style="font-size:11px;color:var(--text-muted);margin-top:6px">Step 1 — Scan this QR code</div>
                </div>

                <!-- Manual entry + instructions -->
                <div style="flex:1;min-width:220px">
                    <div style="font-size:13px;font-weight:700;color:var(--text);margin-bottom:6px">Can't scan? Enter this key manually:</div>
                    <div style="display:flex;align-items:center;gap:8px;margin-bottom:16px">
                        <code id="totpSecretFull" style="flex:1;font-size:13px;font-weight:700;letter-spacing:2px;background:#f3f4f6;border:1px solid var(--border);padding:8px 12px;border-radius:8px;word-break:break-all;color:#111">Loading…</code>
                        <button type="button" onclick="copySecret()" style="flex-shrink:0;padding:8px 12px;border:1px solid var(--border);border-radius:8px;background:var(--bg);cursor:pointer;font-size:12px;color:var(--text)" title="Copy secret">
                            <i class="fa-solid fa-copy" id="copyIcon"></i>
                        </button>
                    </div>
                    <ol style="font-size:13px;color:var(--text-muted);padding-left:18px;line-height:1.8;margin:0 0 16px">
                        <li>Open your authenticator app</li>
                        <li>Tap <strong>+</strong> or <strong>Add account</strong></li>
                        <li>Scan the QR code <em>or</em> choose "Enter key manually"</li>
                        <li>Enter the 6-digit code shown in the app below</li>
                    </ol>
                    <div style="font-size:12px;color:var(--text-muted)">Account name: <strong><?= e($user['email'] ?? 'your account') ?></strong><br>Issuer: <strong>Byabsayee</strong></div>
                </div>
            </div>

            <!-- Verify & Enable form -->
            <div style="border-top:1px solid var(--border);padding-top:18px">
                <div style="font-size:13px;font-weight:700;color:var(--text);margin-bottom:12px">Step 2 — Verify the code, then confirm with your password</div>
                <form method="POST" action="/profile/security/2fa" id="totpVerifyForm">
                    <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                    <input type="hidden" name="action" value="add_method">
                    <input type="hidden" name="two_fa_method" value="app">
                    <div style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap">
                        <div class="fg" style="margin:0;flex:1;min-width:140px">
                            <label style="font-size:12px;font-weight:600;display:block;margin-bottom:5px">6-digit code from your app</label>
                            <input type="text" name="totp_code" id="totpCodeInput" inputmode="numeric" maxlength="6" placeholder="000000" autofocus
                                   style="width:100%;padding:10px 14px;border:2px solid var(--border);border-radius:10px;font-size:22px;font-weight:700;letter-spacing:6px;text-align:center;background:#fff;color:#111">
                        </div>
                        <div class="fg" style="margin:0;flex:1;min-width:160px">
                            <label style="font-size:12px;font-weight:600;display:block;margin-bottom:5px">Your current password</label>
                            <input type="password" name="confirm_password" placeholder="Enter password"
                                   style="width:100%;padding:10px 14px;border:2px solid var(--border);border-radius:10px;font-size:14px;background:#fff;color:#111">
                        </div>
                        <button type="submit" class="btn btn-primary" style="white-space:nowrap;padding:11px 20px">
                            <i class="fa-solid fa-shield-check"></i> Enable Authenticator
                        </button>
                    </div>
                </form>
                <div style="margin-top:10px">
                    <a href="?tab=security" style="font-size:13px;color:var(--text-muted);text-decoration:none">
                        <i class="fa-solid fa-arrow-left" style="font-size:11px"></i> Cancel
                    </a>
                </div>
            </div>
        </div>

        <script>
        // Load QR and secret for the setup panel
        fetch('/profile/security/totp-setup')
            .then(r => r.json())
            .then(d => {
                document.getElementById('qrSpinner').style.display = 'none';
                if (d.qr_data_uri) {
                    const img = document.getElementById('totpQrFull');
                    img.src = d.qr_data_uri;
                    img.style.display = 'block';
                } else if (d.uri) {
                    // Fallback: show link if image fails
                    document.getElementById('qrSpinner').innerHTML = '<a href="' + d.uri + '" style="color:var(--brand);font-size:12px">Open in authenticator app</a>';
                    document.getElementById('qrSpinner').style.display = 'flex';
                }
                if (d.secret) {
                    document.getElementById('totpSecretFull').textContent = d.secret;
                    document.getElementById('totpSecretFull').dataset.secret = d.secret;
                }
                // Auto-focus code input
                document.getElementById('totpCodeInput').focus();
            })
            .catch(() => {
                document.getElementById('qrSpinner').innerHTML = '⚠ Could not load QR';
            });

        function copySecret() {
            const secret = document.getElementById('totpSecretFull').dataset.secret || document.getElementById('totpSecretFull').textContent;
            navigator.clipboard.writeText(secret).then(() => {
                const icon = document.getElementById('copyIcon');
                icon.className = 'fa-solid fa-check';
                setTimeout(() => icon.className = 'fa-solid fa-copy', 2000);
            });
        }

        // Auto-advance: when 6 digits entered, focus password field
        document.getElementById('totpCodeInput').addEventListener('input', function() {
            this.value = this.value.replace(/\D/g,'').slice(0,6);
            if (this.value.length === 6) {
                const pwdField = document.querySelector('#totpVerifyForm input[name="confirm_password"]');
                if (pwdField && !pwdField.value) pwdField.focus();
            }
        });
        </script>
        <?php endif; ?>


        <?php if ($tfa2Enabled): ?>
        <!-- Disable all button -->
        <form method="POST" action="/profile/security/2fa" id="disableAllForm">
            <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
            <input type="hidden" name="action" value="disable_all">
            <input type="hidden" name="confirm_password" id="disableAllPwd">
            <button type="button" class="btn btn-secondary" onclick="openDisableAllModal()" style="border-color:rgba(239,68,68,.4);color:#ef4444">
                <i class="fa-solid fa-shield-xmark"></i> Disable All 2FA
            </button>
        </form>
        <?php endif; ?>
    </div>

    <!-- Active Sessions -->
    <div class="pe-panel">
        <h2><i class="fa-solid fa-desktop" style="color:var(--brand);margin-right:8px"></i>Active Sessions</h2>
        <p class="panel-desc">Devices and browsers signed into your account. The current session is highlighted.</p>

        <?php
        $currentSessId = session_id();
        $hasOther = false;
        foreach ($sessions as $sess) { if ($sess['session_id'] !== $currentSessId) { $hasOther = true; break; } }
        ?>

        <?php if (empty($sessions)): ?>
        <div style="font-size:13px;color:var(--text-muted);padding:12px 0">Only your current session is active.</div>
        <?php else: ?>
        <?php foreach ($sessions as $sess):
            $isCurrent = ($sess['session_id'] === $currentSessId);
            $ua = $sess['user_agent'] ?? 'Unknown browser';
            // Parse device type from UA
            $deviceIcon = 'fa-desktop';
            if (preg_match('/Mobile|Android|iPhone|iPad/i', $ua)) $deviceIcon = 'fa-mobile-screen-button';
            elseif (preg_match('/Tablet/i', $ua)) $deviceIcon = 'fa-tablet-screen-button';
            // Shorten UA
            $uaShort = $ua;
            if (preg_match('/Chrome\/[\d.]+/', $ua, $m)) $uaShort = 'Chrome ' . explode('/', $m[0])[1];
            elseif (preg_match('/Firefox\/[\d.]+/', $ua, $m)) $uaShort = 'Firefox ' . explode('/', $m[0])[1];
            elseif (preg_match('/Safari\/[\d.]+/', $ua, $m) && !str_contains($ua,'Chrome')) $uaShort = 'Safari';
            elseif (preg_match('/Edg\/[\d.]+/', $ua, $m)) $uaShort = 'Edge ' . explode('/', $m[0])[1];
            $lastActive = !empty($sess['last_active_at']) ? date('d M Y, H:i', strtotime($sess['last_active_at'])) : 'Unknown';
        ?>
        <div class="session-item">
            <div class="session-icon <?= $isCurrent ? 'session-current' : '' ?>">
                <i class="fa-solid <?= $deviceIcon ?>"></i>
            </div>
            <div style="flex:1">
                <strong style="font-size:13px;color:var(--text)"><?= e($uaShort) ?><?= $isCurrent ? ' <span style="color:var(--brand);font-size:11px;font-weight:700">● You</span>' : '' ?></strong>
                <div style="font-size:12px;color:var(--text-muted);margin-top:1px">
                    IP: <?= e($sess['ip_address'] ?? '—') ?> &nbsp;·&nbsp; Last active: <?= $lastActive ?>
                </div>
            </div>
            <?php if ($isCurrent): ?>
            <span style="font-size:11px;background:rgba(26,107,74,.1);color:var(--brand);padding:3px 8px;border-radius:20px;font-weight:700">Current</span>
            <?php else: ?>
            <form method="POST" action="/profile/security/sessions/revoke" style="margin:0">
                <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                <input type="hidden" name="session_db_id" value="<?= (int)($sess['id'] ?? 0) ?>">
                <button type="submit" class="btn btn-sm" style="background:none;border:1px solid #e5e7eb;color:#ef4444;padding:4px 10px;font-size:12px;border-radius:8px;cursor:pointer"
                        onclick="return confirm('Sign out this session?')">
                    <i class="fa-solid fa-right-from-bracket"></i> Sign out
                </button>
            </form>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>

        <?php if ($hasOther): ?>
        <div style="margin-top:16px">
            <button type="button" class="btn btn-secondary btn-sm" onclick="openSessionsModal()">
                <i class="fa-solid fa-right-from-bracket"></i> Sign Out All Other Sessions
            </button>
        </div>
        <?php endif; ?>
    </div>

    <!-- Danger Zone -->
    <div class="pe-panel" style="border-color:rgba(239,68,68,.3)">
        <h2 style="color:#ef4444"><i class="fa-solid fa-triangle-exclamation" style="margin-right:8px"></i>Danger Zone</h2>
        <p class="panel-desc">Irreversible account actions. Proceed with caution.</p>
        <div style="background:rgba(239,68,68,.05);border:1px solid rgba(239,68,68,.2);border-radius:10px;padding:16px;display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap">
            <div>
                <strong style="font-size:14px;color:#ef4444">Delete My Account</strong>
                <div style="font-size:12px;color:var(--text-muted);margin-top:3px">Permanently remove your account and all data. Cannot be undone.</div>
            </div>
            <button type="button" class="btn btn-sm" style="background:#ef4444;color:#fff;border:none;white-space:nowrap"
                    onclick="if(confirm('Are you absolutely sure? This cannot be undone.')) window.location='/profile/security/delete-account'">
                <i class="fa-solid fa-trash"></i> Delete Account
            </button>
        </div>
    </div>

    <!-- ── Password-gate modals ──────────────────────────────────────── -->
    <style>
    .sec-modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,.55) !important;z-index:1000;display:flex;align-items:center;justify-content:center;padding:20px}
    .sec-modal{background:#ffffff;border-radius:16px;padding:28px;width:100%;max-width:400px;box-shadow:0 20px 60px rgba(0,0,0,.25)}
    .dark .sec-modal,[data-theme="dark"] .sec-modal{background:#1e2530}
    .sec-modal h3{font-size:16px;font-weight:700;margin:0 0 6px;color:var(--text,#111)}
    .sec-modal p{font-size:13px;color:var(--text-muted,#666);margin:0 0 18px;line-height:1.5}
    .sec-modal .fg{margin-bottom:14px}
    .sec-modal .btn-row{display:flex;gap:10px;justify-content:flex-end}
    .sec-modal input[type="password"],.sec-modal input[type="text"]{background:#f9fafb;color:#111;border:1px solid #d1d5db}
    .dark .sec-modal input[type="password"],.dark .sec-modal input[type="text"],[data-theme="dark"] .sec-modal input{background:#2a3240;color:#e5e7eb;border-color:#374151}
    </style>

    <!-- Add 2FA method modal -->
    <div class="sec-modal-overlay" id="addMethodModal" style="display:none">
        <div class="sec-modal">
            <h3 id="addModalTitle">Enable 2FA Method</h3>
            <p>Enter your current password to confirm.</p>
            <form method="POST" action="/profile/security/2fa" id="addMethodForm">
                <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="add_method">
                <input type="hidden" name="two_fa_method" id="addMethodInput">
                <div class="fg">
                    <label style="font-size:13px;font-weight:600;display:block;margin-bottom:6px">Current Password</label>
                    <input type="password" name="confirm_password" id="addMethodPwd" placeholder="Enter your password" style="width:100%;padding:9px 12px;border:1px solid var(--border);border-radius:8px;font-size:14px;background:var(--input-bg,var(--bg));color:var(--text)" required>
                </div>
                <!-- TOTP code field (for app method) -->
                <div class="fg" id="totpCodeField" style="display:none">
                    <label style="font-size:13px;font-weight:600;display:block;margin-bottom:6px">Authenticator Code</label>
                    <input type="text" name="totp_code" inputmode="numeric" maxlength="6" placeholder="000000" style="width:100%;padding:9px 12px;border:1px solid var(--border);border-radius:8px;font-size:18px;font-weight:700;letter-spacing:4px;text-align:center;background:var(--input-bg,var(--bg));color:var(--text)">
                    <div id="totpQrArea" style="margin-top:12px;text-align:center;display:none">
                        <img id="modalTotpQr" src="" style="width:140px;height:140px;border-radius:8px;border:1px solid var(--border)">
                        <div style="font-size:11px;color:var(--text-muted);margin-top:6px">Scan with your authenticator app</div>
                        <code id="modalTotpSecret" style="font-size:10px;display:block;margin-top:4px;color:var(--brand)"></code>
                    </div>
                </div>
                <div class="btn-row">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('addMethodModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Continue</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Remove 2FA method modal -->
    <div class="sec-modal-overlay" id="removeMethodModal" style="display:none">
        <div class="sec-modal">
            <h3 id="removeModalTitle">Remove 2FA Method</h3>
            <p id="removeModalDesc">Enter your current password to remove this method.</p>
            <form method="POST" action="/profile/security/2fa" id="removeMethodForm">
                <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="remove_method">
                <input type="hidden" name="two_fa_method" id="removeMethodInput">
                <div class="fg">
                    <label style="font-size:13px;font-weight:600;display:block;margin-bottom:6px">Current Password</label>
                    <input type="password" name="confirm_password" placeholder="Enter your password" style="width:100%;padding:9px 12px;border:1px solid var(--border);border-radius:8px;font-size:14px;background:var(--input-bg,var(--bg));color:var(--text)" required>
                </div>
                <div class="btn-row">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('removeMethodModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary" style="background:#ef4444">Remove</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Disable all 2FA modal -->
    <div class="sec-modal-overlay" id="disableAllModal" style="display:none">
        <div class="sec-modal">
            <h3>Disable All 2FA</h3>
            <p>This will remove all 2FA methods and make your account less secure. Enter your password to confirm.</p>
            <div class="fg">
                <label style="font-size:13px;font-weight:600;display:block;margin-bottom:6px">Current Password</label>
                <input type="password" id="disableAllPwdInput" placeholder="Enter your password" style="width:100%;padding:9px 12px;border:1px solid var(--border);border-radius:8px;font-size:14px;background:var(--input-bg,var(--bg));color:var(--text)" required>
            </div>
            <div class="btn-row">
                <button type="button" class="btn btn-secondary" onclick="closeModal('disableAllModal')">Cancel</button>
                <button type="button" class="btn btn-primary" style="background:#ef4444" onclick="submitDisableAll()">Disable All</button>
            </div>
        </div>
    </div>


    <!-- Sign out sessions modal -->
    <div class="sec-modal-overlay" id="sessionsModal" style="display:none">
        <div class="sec-modal">
            <h3>Sign Out Other Sessions</h3>
            <p>This will sign out all other devices. Enter your password to confirm.</p>
            <form method="POST" action="/profile/security/sessions">
                <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="logout_all">
                <div class="fg">
                    <label style="font-size:13px;font-weight:600;display:block;margin-bottom:6px">Current Password</label>
                    <input type="password" name="confirm_password" placeholder="Enter your password" style="width:100%;padding:9px 12px;border:1px solid var(--border);border-radius:8px;font-size:14px;background:var(--input-bg,var(--bg));color:var(--text)" required>
                </div>
                <div class="btn-row">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('sessionsModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Sign Out Others</button>
                </div>
            </form>
        </div>
    </div>

    <script>
    function closeModal(id) { document.getElementById(id).style.display='none'; }
    document.querySelectorAll('.sec-modal-overlay').forEach(m => {
        m.addEventListener('click', e => { if(e.target===m) m.style.display='none'; });
    });
    function openAddModal(method, label) {
        // 'app' method now uses a dedicated page-based setup panel (?setup_totp=1)
        // This modal is only used for email/whatsapp methods
        document.getElementById('addModalTitle').textContent = 'Enable ' + label;
        document.getElementById('addMethodInput').value = method;
        document.getElementById('addMethodPwd').value = '';
        const totpField = document.getElementById('totpCodeField');
        const totpQrArea = document.getElementById('totpQrArea');
        if (totpField) totpField.style.display = 'none';
        document.getElementById('addMethodModal').style.display = 'flex';
        setTimeout(() => document.getElementById('addMethodPwd').focus(), 100);
    }
    function openRemoveModal(method, label) {
        document.getElementById('removeModalTitle').textContent = 'Remove ' + label;
        document.getElementById('removeModalDesc').textContent = 'Enter your password to remove ' + label + ' from your 2FA methods.';
        document.getElementById('removeMethodInput').value = method;
        document.getElementById('removeMethodModal').style.display = 'flex';
    }
    function openDisableAllModal() {
        document.getElementById('disableAllPwdInput').value = '';
        document.getElementById('disableAllModal').style.display = 'flex';
        setTimeout(() => document.getElementById('disableAllPwdInput').focus(), 100);
    }
    function submitDisableAll() {
        const pwd = document.getElementById('disableAllPwdInput').value;
        if (!pwd) { document.getElementById('disableAllPwdInput').focus(); return; }
        document.getElementById('disableAllPwd').value = pwd;
        document.getElementById('disableAllForm').submit();
    }
    function openSessionsModal() {
        document.getElementById('sessionsModal').style.display = 'flex';
    }
    </script>

    <?php endif; ?>

    </div><!-- /.pe-body -->
</div><!-- /.pe-wrap -->

<script>
function previewAvatar(input) {
    if (!input.files || !input.files[0]) return;
    const reader = new FileReader();
    reader.onload = e => {
        const img = document.getElementById('avatarPreviewImg');
        const init = document.getElementById('avatarInitial');
        img.src = e.target.result; img.style.display = 'block';
        if (init) init.style.display = 'none';
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
function updateDesignation(sel) {
    const opt = sel.options[sel.selectedIndex];
    const des = opt.dataset.designation || '';
    const inp = document.getElementById('designationInput');
    if (inp && des) inp.value = des;
}
function addEduRow() {
    const list = document.getElementById('eduList');
    const div = document.createElement('div');
    div.className = 'repeat-item';
    div.innerHTML = `<div class="item-fields" style="grid-template-columns:1fr 1fr">
        <div class="fg" style="margin:0"><label style="font-size:11px">Institute Name *</label><input type="text" name="institute[]" placeholder="e.g. Dhaka University"></div>
        <div class="fg" style="margin:0"><label style="font-size:11px">Subject</label><input type="text" name="subject[]" placeholder="e.g. Computer Science"></div>
        <div class="fg" style="margin:0"><label style="font-size:11px">From Year</label><input type="number" name="from_year[]" min="1950" max="2050" placeholder="2018"></div>
        <div class="fg" style="margin:0"><label style="font-size:11px">To Year</label><input type="number" name="to_year[]" min="1950" max="2050" placeholder="2022"></div>
    </div>
    <button type="button" class="repeat-del" onclick="this.closest('.repeat-item').remove()"><i class="fa-solid fa-xmark"></i></button>`;
    list.appendChild(div);
}
function addGradeRow() {
    const list = document.getElementById('gradeList');
    const div = document.createElement('div');
    div.className = 'repeat-item';
    div.innerHTML = `<div class="item-fields" style="grid-template-columns:1fr 1fr 1fr 1fr">
        <div class="fg" style="margin:0"><label style="font-size:11px">Level *</label><input type="text" name="grade_level[]" placeholder="SSC"></div>
        <div class="fg" style="margin:0"><label style="font-size:11px">Result</label><input type="text" name="grade_result[]" placeholder="GPA 5.00"></div>
        <div class="fg" style="margin:0"><label style="font-size:11px">Board</label><input type="text" name="grade_board[]" placeholder="Dhaka Board"></div>
        <div class="fg" style="margin:0"><label style="font-size:11px">Year</label><input type="number" name="grade_year[]" min="1990" max="2050" placeholder="2020"></div>
    </div>
    <button type="button" class="repeat-del" onclick="this.closest('.repeat-item').remove()"><i class="fa-solid fa-xmark"></i></button>`;
    list.appendChild(div);
}
function addExpRow() {
    const list = document.getElementById('expList');
    const div = document.createElement('div');
    div.className = 'repeat-item';
    div.innerHTML = `<div class="item-fields" style="grid-template-columns:1fr 1fr">
        <div class="fg" style="margin:0"><label style="font-size:11px">Organisation / Company *</label><input type="text" name="exp_org[]" required placeholder="e.g. Acme Corporation"></div>
        <div class="fg" style="margin:0"><label style="font-size:11px">Job Title / Designation *</label><input type="text" name="exp_title[]" required placeholder="e.g. Senior Developer"></div>
        <div class="fg" style="margin:0"><label style="font-size:11px">Employment Type</label>
            <select name="exp_type[]"><option value="full_time">Full-time</option><option value="part_time">Part-time</option><option value="contract">Contract</option><option value="freelance">Freelance</option><option value="internship">Internship</option><option value="volunteer">Volunteer</option></select></div>
        <div class="fg" style="margin:0"><label style="font-size:11px">Location</label><input type="text" name="exp_location[]" placeholder="e.g. Dhaka, Bangladesh"></div>
        <div class="fg" style="margin:0"><label style="font-size:11px">Start Date</label><input type="date" name="exp_start[]"></div>
        <div class="fg" style="margin:0"><label style="font-size:11px">End Date</label><input type="date" name="exp_end[]" class="exp-end-input"></div>
        <div style="grid-column:span 2"><label style="display:flex;align-items:center;gap:6px;font-size:12px;cursor:pointer;font-weight:500">
            <input type="checkbox" name="exp_current[]" value="1" onchange="toggleEndDate(this)"> I currently work here
        </label></div>
        <div class="fg" style="grid-column:span 2;margin:0"><label style="font-size:11px">Description / Responsibilities</label>
            <textarea name="exp_desc[]" rows="2" placeholder="Brief description of your role and achievements..."></textarea></div>
    </div>
    <button type="button" class="repeat-del" onclick="this.closest('.repeat-item').remove()"><i class="fa-solid fa-xmark"></i></button>`;
    list.appendChild(div);
}
function toggleEndDate(cb) {
    const endInput = cb.closest('.item-fields').querySelector('input[name="exp_end[]"]');
    if (endInput) { endInput.disabled = cb.checked; if (cb.checked) endInput.value = ''; }
}
const platformIcons = <?= json_encode($platformIcons) ?>;
function addSocialRow() {
    const list = document.getElementById('socialList');
    const platforms = <?= json_encode(array_map('ucfirst', $platforms)) ?>;
    const keys = <?= json_encode($platforms) ?>;
    const opts = keys.map((k,i) => `<option value="${k}">${platforms[i]}</option>`).join('');
    const div = document.createElement('div');
    div.className = 'social-platform-row';
    div.innerHTML = `<div class="social-platform-icon"><i class="fa-brands fa-linkedin"></i></div>
        <select name="platform[]" style="width:140px" onchange="updateSocialIcon(this)">${opts}</select>
        <input type="url" name="social_url[]" placeholder="https://..." style="flex:1">
        <button type="button" onclick="this.closest('.social-platform-row').remove()" class="repeat-del"><i class="fa-solid fa-xmark"></i></button>`;
    list.appendChild(div);
}
function updateSocialIcon(sel) {
    const icon = platformIcons[sel.value] || 'fa-link';
    const el = sel.closest('.social-platform-row').querySelector('.social-platform-icon i');
    if (el) el.className = 'fa-brands ' + icon;
}
function togglePwd(id, btn) {
    const inp = document.getElementById(id);
    if (!inp) return;
    const show = inp.type === 'password';
    inp.type = show ? 'text' : 'password';
    btn.querySelector('i').className = show ? 'fa-solid fa-eye-slash' : 'fa-solid fa-eye';
}
function checkPwdStrength(val) {
    const fill = document.getElementById('pwdStrengthFill');
    const label = document.getElementById('pwdStrengthLabel');
    if (!fill || !label) return;
    let score = 0;
    if (val.length >= 8) score++;
    if (val.length >= 12) score++;
    if (/[A-Z]/.test(val) && /[a-z]/.test(val)) score++;
    if (/[0-9]/.test(val)) score++;
    if (/[^A-Za-z0-9]/.test(val)) score++;
    const levels = [
        {w:'0%', c:'#ef4444', t:''},
        {w:'20%',c:'#ef4444',t:'Very weak'},
        {w:'40%',c:'#f97316',t:'Weak'},
        {w:'60%',c:'#eab308',t:'Fair'},
        {w:'80%',c:'#22c55e',t:'Strong'},
        {w:'100%',c:'#16a34a',t:'Very strong'},
    ];
    const l = levels[Math.min(score,5)];
    fill.style.width = l.w; fill.style.background = l.c;
    label.textContent = l.t; label.style.color = l.c;
}
function selectTfaCard(radio) {
    document.querySelectorAll('.tfa-method-card').forEach(c => {
        const r = c.querySelector('input[type=radio]');
        c.classList.toggle('selected', r && r.checked);
        const icon = c.querySelector('.tmc-icon');
        if (icon) { icon.style.background = r && r.checked ? 'var(--brand)' : ''; icon.style.borderColor = r && r.checked ? 'var(--brand)' : ''; icon.style.color = r && r.checked ? '#fff' : ''; }
    });
}
</script>

<?php
$content = ob_get_clean();
require BASE_PATH . '/views/partials/layout.php';
