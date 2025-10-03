<?php
require_once __DIR__.'/inc/db.php';
require_once __DIR__.'/partials/header.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* Flag if current session is a logged-in Parent */
$isParentLogged = (isset($_SESSION['role']) && $_SESSION['role'] === 'Parent');

/* Generate CSRF token for parent quick-login modal */
if (empty($_SESSION['parent_csrf'])) {
    $_SESSION['parent_csrf'] = bin2hex(random_bytes(16));
}

/* Dynamic stats */
$statVaccines = $statMothers = $statChildren = '—';

if ($res = $mysqli->query("SELECT COUNT(*) AS cnt FROM vaccine_types WHERE is_active=1")) {
    if ($row = $res->fetch_assoc()) $statVaccines = $row['cnt'];
}
if ($res = $mysqli->query("SELECT COUNT(*) AS cnt FROM mothers_caregivers")) {
    if ($row = $res->fetch_assoc()) $statMothers = $row['cnt'];
}
if ($res = $mysqli->query("SELECT COUNT(*) AS cnt FROM children")) {
    if ($row = $res->fetch_assoc()) $statChildren = $row['cnt'];
}

/* Events */
$events = [];
$stmt = $mysqli->prepare("
  SELECT event_id, event_title, event_description, event_type,
         event_date, event_time, location, created_at
  FROM events
  WHERE is_published = 1
    AND event_date BETWEEN (CURDATE() - INTERVAL 30 DAY) AND (CURDATE() + INTERVAL 90 DAY)
  ORDER BY event_date ASC, event_time ASC
  LIMIT 20
");
if ($stmt && $stmt->execute()) {
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $events[] = $row;
    }
}
?>
<script>
document.addEventListener("DOMContentLoaded", function() {
  const filterBtns = document.querySelectorAll(".filter-controls button");
  const announcementCards = document.querySelectorAll(".announcement-card");

  filterBtns.forEach(btn => {
    btn.addEventListener("click", function() {
      filterBtns.forEach(b => b.classList.remove("active"));
      btn.classList.add("active");
      const filter = btn.getAttribute("data-filter");
      announcementCards.forEach(card => {
        if (filter === "all") {
          card.style.display = "";
        } else {
          card.style.display = (card.getAttribute("data-type") === filter) ? "" : "none";
        }
      });
    });
  });
});
</script>

<section class="hero d-flex align-items-center vh-100" style="background-color:#e7e7e7ff;">
  <div class="container pb-5">
    <div class="row align-items-center g-5 pb-5">
      <div class="col-lg-6">
        <h1 class="display-5 fw-bold mb-3 text-center">Barangay Health & Nutrition Portal</h1>
        <p class="lead mb-4 px-5">
          Stay informed about immunizations, nutrition programs, maternal care, and community health events.
        </p>
        <div class="d-flex justify-content-center gap-3">
          <a href="#announcements" class="btn btn-danger btn-lg px-4">View Announcements</a>
          <a href="#programs" class="btn btn-outline-danger btn-lg px-4">Community Programs</a>
        </div>

        <div class="stats-row mt-5 row g-3">
          <div class="col-4">
            <div class="stat-box text-center" style="border-color:#fd0d0dff;">
              <div class="stat-value" id="statImmunizations"><?= htmlspecialchars($statVaccines) ?></div>
              <div class="stat-label pt-2">Vaccines Tracked</div>
            </div>
          </div>
          <div class="col-4">
            <div class="stat-box text-center" style="border-color:#fd0d0dff;">
              <div class="stat-value" id="statMothers"><?= htmlspecialchars($statMothers) ?></div>
              <div class="stat-label pt-2">Registered Mothers</div>
            </div>
          </div>
          <div class="col-4">
            <div class="stat-box text-center" style="border-color:#fd0d0dff;">
              <div class="stat-value" id="statChildren"><?= htmlspecialchars($statChildren) ?></div>
              <div class="stat-label pt-2">Children Monitored</div>
            </div>
          </div>
        </div>

        <!-- PARENT PORTAL CARD -->
        <div class="card shadow-sm mt-5 border-0" style="border-radius:1.4rem;">
          <div class="card-body p-4">
            <div class="d-flex align-items-center mb-3">
              <div class="me-3 rounded-circle bg-danger text-white d-flex align-items-center justify-content-center" style="width:52px;height:52px;font-size:1.4rem;">
                <i class="bi bi-people"></i>
              </div>
              <div>
                <h5 class="mb-0 fw-bold">Parent / Guardian Portal</h5>
                <small class="text-muted">Access your child’s immunization & growth records</small>
              </div>
            </div>
            <?php if ($isParentLogged): ?>
              <a href="parent_portal.php" class="btn btn-danger w-100 fw-semibold">
                Go to My Portal
              </a>
              <small class="d-block text-center mt-2 text-success">You are logged in.</small>
            <?php else: ?>
              <button class="btn btn-danger w-100 fw-semibold" data-bs-toggle="modal" data-bs-target="#parentLoginModal">
                Login as Parent / Guardian
              </button>
              <small class="d-block text-center mt-2 text-muted">
                New? Request account from BHW/BNS.
              </small>
            <?php endif; ?>
          </div>
        </div>
        <!-- END PARENT PORTAL CARD -->

      </div>
      <div class="col-lg-6 hero-visual">
        <div class="card shadow border-1 announcement-slider" style="border-radius:2rem;overflow:hidden;">
          <div class="card-header bg-danger d-flex justify-content-center align-items-center px-4 pt-3">
            <h2 class="fw-bold text-white">Highlighted Events</h2>
          </div>
          <div class="card-body p-3">
            <?php if (count($events) === 0): ?>
              <div class="p-4 text-center small opacity-75">No announcements available.</div>
            <?php else: ?>
              <div id="highlightCarousel" class="carousel slide" data-bs-ride="carousel">
                <div class="carousel-inner">
                  <?php foreach ($events as $i => $ev):
                        $dateFmt = date('M d, Y', strtotime($ev['event_date']));
                        $ex = mb_strimwidth(strip_tags($ev['event_description'] ?? ''), 0, 140, '...');
                  ?>
                  <div class="carousel-item <?= $i===0 ? 'active' : '' ?>">
                    <div class="p-4">
                      <div class="text-center mb-3">
                        <h3 class="fw-bold my-1 pb-2 px-3" style="border-bottom:3px solid #fd0d0dff;display:inline-block;">
                          <?= htmlspecialchars($ev['event_title']) ?>
                        </h3>
                      </div>
                      <p class="small my-2 fw-bold">
                        <span class="me-2">
                          <i class="bi bi-calendar-event"></i> <?= $dateFmt ?>
                        </span>
                        <?php if (!empty($ev['event_time'])): ?>
                          <span><i class="bi bi-clock"></i> <?= date('h:i A', strtotime($ev['event_time'])) ?></span>
                        <?php endif; ?>
                      </p>
                      <p class="small mb-2"><?= htmlspecialchars($ex) ?></p>
                      <?php if (!empty($ev['location'])): ?>
                        <div class="small fw-bold"><i class="bi bi-geo-alt"></i> <?= htmlspecialchars($ev['location']) ?></div>
                      <?php endif; ?>
                    </div>
                  </div>
                  <?php endforeach; ?>
                </div>
              </div>
            <?php endif; ?>
          </div>
          <div class="card-footer bg-danger small text-end">
            <i class="bi bi-arrow-right-circle"></i>
            <a href="#announcements" class="text-white">See all announcements →</a>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<section id="announcements" class="py-5 section-alt">
  <div class="container">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 my-4 py-5">
      <h2 class="mb-0">Announcements & Events</h2>
      <div class="filter-controls">
        <button class="btn btn-sm btn-outline-secondary active" data-filter="all">All</button>
        <button class="btn btn-sm btn-outline-secondary" data-filter="general">General</button>
        <button class="btn btn-sm btn-outline-secondary" data-filter="health">Health</button>
        <button class="btn btn-sm btn-outline-secondary" data-filter="nutrition">Nutrition</button>
        <button class="btn btn-sm btn-outline-secondary" data-filter="vaccination">Vaccination</button>
        <button class="btn btn-sm btn-outline-secondary" data-filter="feeding">Feeding</button>
      </div>
    </div>
    <div class="row g-4" id="announcementList">
      <?php if (count($events) === 0): ?>
        <div class="col-12">
          <div class="alert alert-info">No announcements posted yet.</div>
        </div>
      <?php else: ?>
        <?php foreach ($events as $ev):
              $type = htmlspecialchars($ev['event_type']);
              $dateFmt = date('M d, Y', strtotime($ev['event_date']));
        ?>
        <div class="col-md-6 col-lg-4 announcement-card" data-type="<?= $type ?>">
          <div class="card h-100 shadow-sm border-0">
            <div class="card-body d-flex flex-column">
              <div class="d-flex justify-content-between align-items-start mb-2">
                <span class="badge text-bg-<?=
                  match($ev['event_type']) {
                    'health' => 'danger',
                    'nutrition' => 'success',
                    'vaccination' => 'warning',
                    'feeding' => 'primary',
                    default => 'secondary'
                  };
                ?>">
                  <?= ucfirst($ev['event_type']) ?>
                </span>
                <small class="fw-bold"><?= $dateFmt ?></small>
              </div>
              <h4 class="card-title mb-2"><?= htmlspecialchars($ev['event_title']) ?></h4>
              <p class="card-text small flex-grow-1">
                <?= htmlspecialchars(mb_strimwidth(strip_tags($ev['event_description'] ?? ''), 0, 160, '...')) ?>
              </p>
              <?php if (!empty($ev['location'])): ?>
                <div class="small fw-bold mb-2">
                  <i class="bi bi-geo-alt"></i> <?= htmlspecialchars($ev['location']) ?>
                </div>
              <?php endif; ?>
              <button class="btn btn-outline-danger btn-sm mt-auto view-announcement"
                data-title="<?= htmlspecialchars($ev['event_title']) ?>"
                data-date="<?= $dateFmt ?>"
                data-time="<?= !empty($ev['event_time']) ? date('h:i A', strtotime($ev['event_time'])) : '—' ?>"
                data-location="<?= htmlspecialchars($ev['location'] ?? '') ?>"
                data-body="<?= htmlspecialchars($ev['event_description'] ?? '') ?>">
                Read More
              </button>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
</section>

<section id="programs" class="py-5">
  <div class="container">
    <h2 class="my-4 pt-5">Community Health & Nutrition Programs</h2>
    <div class="row g-4 py-5">
      <div class="col-md-4">
        <div class="program-box h-100 p-4 rounded-4 border bg-white">
          <div class="icon-circle mb-3 bg-danger-subtle text-danger">
            <i class="bi bi-capsule"></i>
          </div>
          <h5>Child Immunization</h5>
          <p class="small text-secondary">
            Ensuring timely vaccination schedules to protect children from preventable diseases.
          </p>
        </div>
      </div>
      <div class="col-md-4">
        <div class="program-box h-100 p-4 rounded-4 border bg-white">
          <div class="icon-circle mb-3 bg-success-subtle text-success">
            <i class="bi bi-apple"></i>
          </div>
          <h5>Nutrition Monitoring</h5>
            <p class="small text-secondary">
            Regular growth assessments, weight-for-length/height evaluations, and supplementation tracking.
          </p>
        </div>
      </div>
      <div class="col-md-4">
        <div class="program-box h-100 p-4 rounded-4 border bg-white">
          <div class="icon-circle mb-3 bg-warning-subtle text-warning">
            <i class="bi bi-person-heart"></i>
          </div>
          <h5>Maternal Health</h5>
          <p class="small text-secondary">
            Prenatal check-ups and risk screening to support safe and healthy pregnancies.
          </p>
        </div>
      </div>
    </div>
  </div>
</section>

<section id="about" class="py-5 section-alt">
  <div class="container">
    <div class="row g-5 align-items-center">
      <div class="col-lg-6">
        <h2 class="my-4 py-2">About Barangay Sabang Health Initiative</h2>
        <p class="lead">
          A community-centered approach to promoting health, preventing disease, and building resilient families.
        </p>
        <p class="small">
          This portal serves as a trusted information hub. Through collaboration among Admin, Barangay Health Workers (BHW),
          and Barangay Nutrition Scholars (BNS), we support families with accurate data and timely interventions.
        </p>
        <ul class="list-unstyled small">
          <li class="mb-2"><i class="bi bi-check2-circle text-success me-1"></i> Data-driven immunization tracking</li>
          <li class="mb-2"><i class="bi bi-check2-circle text-success me-1"></i> Nutrition and growth surveillance</li>
          <li class="mb-2"><i class="bi bi-check2-circle text-success me-1"></i> Maternal and child health education</li>
        </ul>
      </div>
      <div class="col-lg-6">
        <div class="ratio ratio-16x9 rounded-4 overflow-hidden shadow">
          <iframe
            src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3550.8190585948946!2d121.16785557339493!3d13.946972827182208!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x33bd6b62894b2ff7%3A0x858154ec3465aece!2sSabang%2C%20Lipa%20City%2C%20Batangas!5e0!3m2!1sen!2sph!4v1759315532961!5m2!1sen!2sph"
            style="border:0;" allowfullscreen="" loading="lazy"
            referrerpolicy="no-referrer-when-downgrade"></iframe>
        </div>
      </div>
    </div>
  </div>
</section>

<section id="contact" class="py-5">
  <div class="container">
    <div class="row g-5">
      <div class="col-lg-5">
        <h2 class="my-4 pt-5">Contact & Location</h2>
        <p class="small text-secondary">
          Reach out for schedules, health inquiries, or program participation.
        </p>
        <ul class="list-unstyled small">
          <li><i class="bi bi-telephone text-danger me-2"></i> Health Center: (012) 345-6789</li>
          <li><i class="bi bi-envelope text-danger me-2"></i> health@sabang.gov</li>
            <li><i class="bi bi-geo-alt text-danger me-2"></i> Purok 1, Barangay Sabang</li>
        </ul>
        <div class="alert alert-warning small mt-4 mb-0">
          <strong>Reminder:</strong> Always bring your child’s immunization card during visits.
        </div>
      </div>
      <div class="col-lg-7">
        <div class="card border-0 shadow-sm">
          <div class="card-body">
            <h5 class="mb-3">Feedback / Suggestion</h5>
            <form id="feedbackForm" novalidate>
              <div class="row g-3">
                <div class="col-md-6">
                  <label class="form-label small fw-semibold">Name</label>
                  <input type="text" class="form-control" required>
                </div>
                <div class="col-md-6">
                  <label class="form-label small fw-semibold">Email</label>
                  <input type="email" class="form-control" required>
                </div>
                <div class="col-12">
                  <label class="form-label small fw-semibold">Message</label>
                  <textarea class="form-control" rows="4" required></textarea>
                </div>
                <div class="col-12">
                  <button class="btn btn-danger px-4" type="submit">Send Message</button>
                  <small class="text-success ms-3 d-none" id="feedbackSuccess">Sent!</small>
                </div>
              </div>
            </form>
            <p class="small text-muted mt-3 mb-0">
              This form is a front-end demo only. Integrate backend handling as needed.
            </p>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- Announcement Modal -->
<div class="modal fade" id="announcementModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-scrollable modal-lg modal-fullscreen-sm-down">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title announcement-modal-title"></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="small text-secondary mb-2">
          <span class="announcement-modal-date"></span> •
          <span class="announcement-modal-time"></span>
        </div>
        <div class="announcement-modal-location small mb-3 text-muted d-none"></div>
        <div class="announcement-modal-body small lh-lg"></div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<!-- Parent Quick Login Modal -->
<?php if (!$isParentLogged): ?>
<div class="modal fade" id="parentLoginModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form class="modal-content" method="post" action="auth/login_parent_process.php" novalidate>
      <div class="modal-header">
        <h5 class="modal-title">Parent / Guardian Login</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <?php /* CSRF hidden field already prepared in session */ ?>
        <div class="mb-3">
          <label class="form-label small fw-semibold">Username</label>
          <input type="text" class="form-control" name="username" maxlength="100" required autofocus>
        </div>
        <div class="mb-3">
          <label class="form-label small fw-semibold">Password</label>
          <input type="password" class="form-control" name="password" required>
        </div>
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['parent_csrf']) ?>">
        <div class="alert alert-warning small">
          Kung wala ka pang account, makipag-ugnayan sa Barangay Health Worker.
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-danger fw-semibold px-4" type="submit">Login</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<?php require_once __DIR__.'/partials/footer.php'; ?>