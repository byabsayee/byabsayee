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
.fg input[type=date],.fg select,.fg textarea{width:100%;padding:9px 12px;border:1px solid var(--border);border-radius:8px;background:var(--input-bg,var(--bg));color:var(--text);font-size:14px;box-sizing:border-box;transition:border-color .15s;font-family:inherit}
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
        <a href="?tab=social"     class="<?= $tab==='social'?'active':'' ?>"><i class="fa-solid fa-share-nodes"></i> Social Links</a>
        <div class="nav-group">Privacy</div>
        <a href="?tab=visibility" class="<?= $tab==='visibility'?'active':'' ?>"><i class="fa-solid fa-eye"></i> Visibility</a>
        <div class="nav-group">Print</div>
        <a href="/profile/cv" target="_blank"><i class="fa-solid fa-print"></i> Print CV</a>
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
                'social_links'      => ['Social Links', 'LinkedIn, GitHub, etc.'],
                'relationship_status'=>['Relationship Status', 'Marital/relationship info'],
                'expertise'         => ['Expertise / Skills', 'Skills and competencies'],
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
</script>

<?php
$content = ob_get_clean();
require BASE_PATH . '/views/partials/layout.php';
