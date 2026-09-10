<?php
session_start();

require_once __DIR__ . '/../config/upload_helpers.php';

$step1_error = '';
$has_saved_valid_id_front = !empty($_SESSION['valid_id_front_path']);
$has_saved_valid_id_back = !empty($_SESSION['valid_id_back_path']);
$has_saved_valid_id_pair = $has_saved_valid_id_front && $has_saved_valid_id_back;

$_SESSION['valid_id_attached_confirmed'] = false;

if (!empty($_SESSION['step1_error'])) {
    $step1_error = (string) $_SESSION['step1_error'];
    unset($_SESSION['step1_error']);
}
$id_accept = 'image/jpeg,image/png,image/webp,application/pdf,.jpg,.jpeg,.png,.webp,.pdf';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim((string) ($_POST['full_name'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));

    if ($full_name === '' || $email === '') {
        $step1_error = 'Full name and email are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $step1_error = 'Please enter a valid email address.';
    } elseif (empty($_POST['is_resident']) || empty($_POST['is_legal_age'])) {
        $step1_error = 'Please confirm both residency and legal age requirements.';
    } else {
        $id_front = bpis_store_upload('valid_id_front', 'borrower_ids');
        $id_back = bpis_store_upload('valid_id_back', 'borrower_ids');

        if ($id_front === null || $id_front === '') {
            $step1_error = 'Please attach the front of your valid ID (JPG, PNG, WEBP, or PDF).';
        } elseif ($id_back === null || $id_back === '') {
            $step1_error = 'Please attach the back of your valid ID (JPG, PNG, WEBP, or PDF).';
        } else {
            $_SESSION['full_name'] = $full_name;
            $_SESSION['email'] = $email;
            $_SESSION['is_resident'] = true;
            $_SESSION['is_legal_age'] = true;
            $_SESSION['valid_id_front_path'] = $id_front;
            $_SESSION['valid_id_back_path'] = $id_back;
            unset($_SESSION['valid_id_path']);
            $_SESSION['valid_id_attached_confirmed'] = true;

            header('Location: step2.php');
            exit();
        }
    }
}

$borrow_step = 1;
$borrow_page_title = 'Personal Information';
$borrow_heading = 'Personal information';
$borrow_lead = 'Enter your details and attach the front and back of your valid government-issued ID.';
include __DIR__ . '/includes/borrow_layout_start.php';
?>
        <?php if ($step1_error !== ''): ?>
            <div class="borrow-alert" role="alert"><?= htmlspecialchars($step1_error) ?></div>
        <?php endif; ?>

        <div id="borrowClientIdError" class="borrow-alert" role="alert" style="display:none;"></div>

        <form method="post" action="step1.php" enctype="multipart/form-data" novalidate id="borrowStep1Form">
            <div class="borrow-field">
                <label for="full_name">Full name</label>
                <input type="text" id="full_name" name="full_name" class="borrow-input" autocomplete="name"
                       placeholder="First name, middle initial, last name" required
                       value="<?= htmlspecialchars((string) ($_POST['full_name'] ?? $_SESSION['full_name'] ?? '')) ?>">
            </div>

            <div class="borrow-field">
                <label for="email">Email address</label>
                <input type="email" id="email" name="email" class="borrow-input" autocomplete="email"
                       placeholder="you@email.com" required
                       value="<?= htmlspecialchars((string) ($_POST['email'] ?? $_SESSION['email'] ?? '')) ?>">
            </div>

            <div class="borrow-field">
                <span class="borrow-field-label" id="valid_id_front_label">Valid ID — front <span class="borrow-required">*</span></span>
                <div class="borrow-id-capture" data-borrow-id-capture>
                    <input type="file" id="valid_id_front" name="valid_id_front" class="borrow-id-capture__input"
                           accept="<?= htmlspecialchars($id_accept, ENT_QUOTES) ?>" required
                           aria-labelledby="valid_id_front_label">
                    <div class="borrow-id-capture__actions">
                        <button type="button" class="borrow-id-capture__btn borrow-id-capture__btn--camera" data-id-camera>
                            Take photo
                        </button>
                        <button type="button" class="borrow-id-capture__btn borrow-id-capture__btn--upload" data-id-upload>
                            Upload file
                        </button>
                    </div>
                    <p class="borrow-id-capture__status" data-file-name hidden></p>
                    <div class="borrow-id-capture__preview" data-preview-wrap hidden>
                        <img data-preview-img alt="Preview of ID front">
                    </div>
                </div>
                <p class="borrow-hint">Use your camera or choose a JPG, PNG, WEBP, or PDF of the front side.</p>
            </div>

            <div class="borrow-field">
                <span class="borrow-field-label" id="valid_id_back_label">Valid ID — back <span class="borrow-required">*</span></span>
                <div class="borrow-id-capture" data-borrow-id-capture>
                    <input type="file" id="valid_id_back" name="valid_id_back" class="borrow-id-capture__input"
                           accept="<?= htmlspecialchars($id_accept, ENT_QUOTES) ?>" required
                           aria-labelledby="valid_id_back_label">
                    <div class="borrow-id-capture__actions">
                        <button type="button" class="borrow-id-capture__btn borrow-id-capture__btn--camera" data-id-camera>
                            Take photo
                        </button>
                        <button type="button" class="borrow-id-capture__btn borrow-id-capture__btn--upload" data-id-upload>
                            Upload file
                        </button>
                    </div>
                    <p class="borrow-id-capture__status" data-file-name hidden></p>
                    <div class="borrow-id-capture__preview" data-preview-wrap hidden>
                        <img data-preview-img alt="Preview of ID back">
                    </div>
                </div>
                <p class="borrow-hint">Use your camera or choose a file for the back side. The secretary will review both with your request.</p>
                <?php if (!empty($_SESSION['valid_id_front_path']) && !empty($_SESSION['valid_id_back_path'])): ?>
                    <p class="borrow-hint borrow-hint--ok">
                        ID front and back are already on file for this session.
                        To continue to step 2, please upload (or take a new photo) again to confirm.
                    </p>
                <?php endif; ?>
            </div>

            <label class="borrow-check">
                <input type="checkbox" name="is_resident" id="is_resident" required
                    <?= !empty($_POST['is_resident']) || !empty($_SESSION['is_resident']) ? 'checked' : '' ?>>
                <span>I am a registered voter and bonafide resident of Barangay Bolocboloc, Sibulan, Negros Oriental.</span>
            </label>

            <label class="borrow-check">
                <input type="checkbox" name="is_legal_age" id="is_legal_age" required
                    <?= !empty($_POST['is_legal_age']) || !empty($_SESSION['is_legal_age']) ? 'checked' : '' ?>>
                <span>I am of legal age (25 and above) to borrow property.</span>
            </label>

            <button type="submit" class="borrow-btn">Continue to request details</button>
        </form>

        <div class="borrow-camera-modal" id="borrowIdCameraModal" hidden>
            <div class="borrow-camera-modal__backdrop" data-camera-close></div>
            <div class="borrow-camera-modal__panel" role="dialog" aria-modal="true" aria-labelledby="borrowIdCameraTitle">
                <p class="borrow-camera-modal__title" id="borrowIdCameraTitle">Take a photo of your ID</p>
                <video class="borrow-camera-modal__video" data-camera-video autoplay playsinline muted></video>
                <canvas class="borrow-camera-modal__canvas" data-camera-canvas hidden></canvas>
                <div class="borrow-camera-modal__actions">
                    <button type="button" class="borrow-btn borrow-btn--secondary borrow-btn--sm" data-camera-close>Cancel</button>
                    <button type="button" class="borrow-btn borrow-btn--sm" data-camera-capture>Capture photo</button>
                </div>
                <p class="borrow-hint borrow-camera-modal__error" data-camera-error hidden></p>
            </div>
        </div>
<?php
$borrow_footer_scripts = '<script src="borrow_id_capture.js"></script>';
$borrow_footer_scripts .= '<script>
(function(){
  "use strict";
  document.addEventListener("DOMContentLoaded", function(){
    var form = document.getElementById("borrowStep1Form");
    var frontInput = document.getElementById("valid_id_front");
    var backInput = document.getElementById("valid_id_back");
    var clientErr = document.getElementById("borrowClientIdError");
    var hasSavedPair = ' . ($has_saved_valid_id_pair ? 'true' : 'false') . ';

    function showClientError(msg){
      if (!clientErr) { alert(msg); return; }
      clientErr.textContent = msg;
      clientErr.style.display = "block";
    }

    function hideClientError(){
      if (!clientErr) return;
      clientErr.textContent = "";
      clientErr.style.display = "none";
    }

    function clearInputsIfHaveSavedPair(){
      // Force the user to attach IDs again for the current request.
      if (!hasSavedPair) return;
      if (frontInput && backInput){
        try {
          frontInput.value = "";
          backInput.value = "";
          frontInput.dispatchEvent(new Event("change", { bubbles: true }));
          backInput.dispatchEvent(new Event("change", { bubbles: true }));
        } catch (e) { console.warn("Borrower ID inputs reset failed:", e); }
      }
    }

    clearInputsIfHaveSavedPair();
    if (frontInput) frontInput.addEventListener("change", hideClientError);
    if (backInput) backInput.addEventListener("change", hideClientError);

    if (!form || !frontInput || !backInput) return;

    form.addEventListener("submit", function(e){
      var hasFront = frontInput.files && frontInput.files[0];
      var hasBack = backInput.files && backInput.files[0];
      if (!hasFront){
        e.preventDefault();
        showClientError("Please attach the front of your valid ID (JPG, PNG, WEBP, or PDF).");
        if (frontInput && frontInput.focus) frontInput.focus();
        return;
      }
      if (!hasBack){
        e.preventDefault();
        showClientError("Please attach the back of your valid ID (JPG, PNG, WEBP, or PDF).");
        if (backInput && backInput.focus) backInput.focus();
      }
    });
  });
})();
</script>';
include __DIR__ . '/includes/borrow_layout_end.php';
?>
