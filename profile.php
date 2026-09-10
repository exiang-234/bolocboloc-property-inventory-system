<?php
session_start();
include __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth_helpers.php';
require_once __DIR__ . '/config/admin_profile_helpers.php';


bpis_require_login(['Secretary', 'Treasurer', 'Barangay Captain']);

$admin_id = (int) ($_SESSION['admin_id'] ?? 0);
$close_url = bpis_admin_dashboard_url();
$msg = '';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_profile'])) {
    if (bpis_save_admin_profile($conn, $admin_id, $_POST, $_FILES)) {
        header('Location: profile.php?saved=1');
        exit;
    }
    $err = 'Could not save profile. Full name is required.';
}

$profile = bpis_load_admin_profile($conn, $admin_id);
if (!$profile) {
    header('Location: login.php');
    exit;
}

$bpis_user_fullname = (string) ($profile['fullname'] ?? '');
$bpis_user_role = (string) ($profile['role'] ?? '');
$bpis_profile_avatar = bpis_admin_profile_avatar_url($profile);
$bpis_page_title = 'Profile Information';

if (!empty($_GET['saved'])) {
    $msg = 'Profile information saved.';
}

$bpis_extra_head = <<<'HTML'
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.2/cropper.min.css" crossorigin="anonymous">
<script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.2/cropper.min.js" crossorigin="anonymous"></script>
HTML;

include __DIR__ . '/includes/profile_layout_start.php';
?>
            <div class="profile-page-wrap">
                <div class="profile-info-card">
                    <div class="profile-info-body">
                        <?php if ($msg): ?>
                            <div class="profile-flash ok"><?= htmlspecialchars($msg) ?></div>
                        <?php endif; ?>
                        <?php if ($err): ?>
                            <div class="profile-flash err"><?= htmlspecialchars($err) ?></div>
                        <?php endif; ?>

                        <form method="post" enctype="multipart/form-data" id="profileForm" class="profile-form-inner">
                            <input type="hidden" name="save_profile" value="1">

                            <div class="profile-form-grid">
                                <aside class="profile-avatar-block">
                                    <h3 class="profile-avatar-title">Profile</h3>
                                    <div class="profile-avatar-frame">
                                        <img src="<?= htmlspecialchars($bpis_profile_avatar) ?>" alt="Profile photo" id="profileAvatarPreview"
                                             onerror="this.onerror=null;this.src='https://ui-avatars.com/api/?name=<?= rawurlencode($bpis_user_fullname) ?>&background=174C7D&color=fff&size=256';">
                                    </div>
                                    <div class="profile-photo-upload">
                                        <button type="button" class="btn-profile-upload" id="profilePhotoChooseBtn">
                                            <i class="fa-solid fa-camera" aria-hidden="true"></i>
                                            <span>Change photo</span>
                                        </button>
                                        <input type="file" name="profile_photo" id="profilePhotoInput" class="profile-photo-input-hidden"
                                               accept="image/jpeg,image/png,image/webp">
                                        <p class="profile-photo-hint">Optional · JPG, PNG or WebP</p>
                                        <p class="profile-photo-filename" id="profilePhotoFilename">Using current photo</p>
                                    </div>
                                </aside>

                                <div class="profile-fields-main">
                                    <div class="profile-fields-row profile-fields-row-2">
                                        <div class="profile-field">
                                            <label for="fullname">Full Name</label>
                                            <input type="text" name="fullname" id="fullname" required
                                                   value="<?= htmlspecialchars($profile['fullname'] ?? '') ?>"
                                                   placeholder="Your full name">
                                        </div>
                                        <div class="profile-field">
                                            <label>Role</label>
                                            <div class="profile-field-value readonly"><?= htmlspecialchars($bpis_user_role) ?></div>
                                        </div>
                                    </div>

                                    <div class="profile-field">
                                        <label>Email (login)</label>
                                        <div class="profile-field-value readonly"><?= htmlspecialchars($profile['email'] ?? $profile['username'] ?? '') ?></div>
                                    </div>

                                    <div class="profile-fields-row profile-fields-row-2">
                                        <div class="profile-field">
                                            <label for="contact_number">Contact number</label>
                                            <input type="tel" name="contact_number" id="contact_number"
                                                   value="<?= htmlspecialchars($profile['contact_number'] ?? '') ?>"
                                                   placeholder="e.g. 09171234567">
                                        </div>
                                        <div class="profile-field">
                                            <label for="address">Address / Purok</label>
                                            <input type="text" name="address" id="address"
                                                   value="<?= htmlspecialchars($profile['address'] ?? '') ?>"
                                                   placeholder="Barangay address or purok">
                                        </div>
                                    </div>

                                    <div class="profile-field profile-field-bio">
                                        <label for="bio">Additional information</label>
                                        <textarea name="bio" id="bio" placeholder="Notes, position, or other details you want on your profile"><?= htmlspecialchars($profile['bio'] ?? '') ?></textarea>
                                    </div>
                                </div>
                            </div>

                            <div class="profile-form-actions">
                                <button type="submit" class="btn-profile-save">Save information</button>
                                <a href="<?= htmlspecialchars($close_url) ?>" class="btn-profile-close-bottom">Close</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

    <div class="profile-crop-overlay" id="profileCropOverlay" aria-hidden="true">
        <div class="profile-crop-modal" role="dialog" aria-labelledby="profileCropTitle">
            <div class="profile-crop-modal-header">
                <h3 id="profileCropTitle">Crop profile photo</h3>
                <p>Drag to position · square crop for your avatar</p>
            </div>
            <div class="profile-crop-stage">
                <img src="" alt="" id="profileCropImage">
            </div>
            <div class="profile-crop-actions">
                <button type="button" class="btn-profile-crop-cancel" id="profileCropCancel">Cancel</button>
                <button type="button" class="btn-profile-crop-apply" id="profileCropApply">Apply photo</button>
            </div>
        </div>
    </div>
<?php
$bpis_layout_footer_scripts = '<script src="js/profile_photo_crop.js"></script>';
include __DIR__ . '/includes/profile_layout_end.php';
