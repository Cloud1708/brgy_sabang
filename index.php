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
          card.classList.add("animate__fadeIn");
        } else {
          card.style.display = (card.getAttribute("data-type") === filter) ? "" : "none";
          if (card.style.display === "") card.classList.add("animate__fadeIn");
        }
      });
    });
  });

  // Announcement modal population
  document.querySelectorAll(".view-announcement").forEach(btn => {
    btn.addEventListener("click", function() {
      const modal = document.getElementById("announcementModal");
      modal.querySelector(".announcement-modal-title").textContent = this.getAttribute("data-title");
      modal.querySelector(".announcement-modal-date").textContent = this.getAttribute("data-date");
      modal.querySelector(".announcement-modal-time").textContent = this.getAttribute("data-time");
      const location = modal.querySelector(".announcement-modal-location");
      const locText = this.getAttribute("data-location");
      if (locText) {
        location.textContent = locText;
        location.classList.remove("d-none");
      } else {
        location.classList.add("d-none");
      }
      modal.querySelector(".announcement-modal-body").textContent = this.getAttribute("data-body");
    });
  });

  // Feedback form handling
  document.getElementById("feedbackForm").addEventListener("submit", function(e) {
    e.preventDefault();
    const inputs = this.querySelectorAll("input, textarea");
    let valid = true;
    inputs.forEach(input => {
      if (!input.value) {
        input.classList.add("is-invalid");
        valid = false;
      } else {
        input.classList.remove("is-invalid");
        input.classList.add("is-valid");
      }
    });
    if (valid) {
      const submitBtn = this.querySelector("button[type='submit']");
      submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Sending...';
      setTimeout(() => {
        submitBtn.innerHTML = 'Send Message';
        document.getElementById("feedbackSuccess").classList.remove("d-none");
      }, 1000);
    }
  });
});
</script>

<section class="hero d-flex align-items-center vh-100 position-relative" data-aos="fade-up" style="background-image: linear-gradient(rgba(0,0,0,0.75), rgba(0,0,0,0.75)), url('assets/img/brgy-sabang.jpg'); background-size: cover; background-position: center; background-repeat: no-repeat;">
  <div class="container pb-5">
    <div class="row align-items-center g-5 pb-5">
      <div class="col-lg-6">
        <h1 class="display-4 fw-bold mb-3 text-center text-white" style="color:#212529;">Barangay Health & Nutrition Portal</h1>
        <p class="lead mb-4 px-5 text-white">
          Stay informed about immunizations, nutrition programs, maternal care, and community health events.
        </p>
        <div class="d-flex justify-content-center gap-3">
          <a href="#updates" class="btn btn-danger btn-lg px-4" aria-label="View all announcements">View Announcements</a>
          <a href="#updates" class="btn btn-danger btn-lg px-4" aria-label="View community programs">Community Programs</a>
        </div>

        <!-- PARENT PORTAL CARD -->
        <div class="card shadow-sm mt-5 border-0 parent-card" style="border-radius:1.4rem;">
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
              <a href="parent_portal" class="btn btn-danger w-100 fw-semibold">
                <i class="bi bi-lock me-1"></i> Go to My Portal
              </a>
              <small class="d-block text-center mt-2 text-success">You are logged in.</small>
            <?php else: ?>
              <a href="parent_login" class="btn btn-danger w-100 fw-semibold" aria-label="Login to parent portal">
                <i class="bi bi-lock me-1"></i> Login as Parent / Guardian
              </a>
              <small class="d-block text-center mt-2 text-muted">
                New? Request account from BHW/BNS.
              </small>
            <?php endif; ?>
          </div>
        </div>
        <!-- END PARENT PORTAL CARD -->
      </div>
      <div class="col-lg-6 hero-visual">
        <div class="card shadow border-0 announcement-slider carousel-fade" style="border-radius:2.5rem;overflow:hidden;">
          <div class="card-header bg-danger d-flex justify-content-center align-items-center px-4 pt-3">
            <h2 class="fw-bold text-white">Highlighted Events</h2>
          </div>
          <div class="card-body px-3 py-5 my-5">
            <?php if (count($events) === 0): ?>
              <div class="p-4 text-center fs-4 opacity-75 my-5 py-5">No announcements available.</div>
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
                <button class="carousel-control-prev" type="button" data-bs-target="#highlightCarousel" data-bs-slide="prev">
                  <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                  <span class="visually-hidden">Previous</span>
                </button>
                <button class="carousel-control-next" type="button" data-bs-target="#highlightCarousel" data-bs-slide="next">
                  <span class="carousel-control-next-icon" aria-hidden="true"></span>
                  <span class="visually-hidden">Next</span>
                </button>
              </div>
            <?php endif; ?>
          </div>
          <div class="card-footer bg-danger small text-end">
            <i class="bi bi-arrow-right-circle"></i>
            <a href="#announcements" class="text-white" aria-label="See all announcements">See all announcements →</a>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<section id="updates" class="py-5 section-alt" data-aos="fade-up">
  <div class="container">
    <div class="row g-5 align-items-start mt-5">
      <!-- Announcements Column -->
      <div class="col-12 col-lg-6" id="announcements">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 my-2 py-2">
          <h2 class="mb-0">Announcements & Events</h2>
          <div class="filter-controls">
            <button class="btn btn-sm btn-outline-danger active" data-filter="all">All</button>
            <button class="btn btn-sm btn-outline-danger" data-filter="general">General</button>
            <button class="btn btn-sm btn-outline-danger" data-filter="health">Health</button>
            <button class="btn btn-sm btn-outline-danger" data-filter="nutrition">Nutrition</button>
            <button class="btn btn-sm btn-outline-danger" data-filter="vaccination">Vaccination</button>
            <button class="btn btn-sm btn-outline-danger" data-filter="feeding">Feeding</button>
          </div>
        </div>
        <div class="row g-4" id="announcementList">
          <?php if (count($events) === 0): ?>
            <div class="col-12 pt-3">
              <div class="alert alert-info">No announcements posted yet.</div>
            </div>
          <?php else: ?>
            <?php foreach ($events as $ev):
                  $type = htmlspecialchars($ev['event_type']);
                  $dateFmt = date('M d, Y', strtotime($ev['event_date']));
            ?>
            <div class="col-12 pt-3 announcement-card" data-type="<?= $type ?>" data-aos="fade-up">
              <div class="card h-100 shadow-sm border-0">
                <div class="card-body d-flex flex-column">
                  <div class="d-flex justify-content-between align-items-start mb-2">
                    <span class="badge text-bg-<?= match($ev['event_type']) {
                        'health' => 'danger',
                        'nutrition' => 'success',
                        'vaccination' => 'warning',
                        'feeding' => 'primary',
                        default => 'secondary'
                    }; ?>">
                      <?= ucfirst($ev['event_type']) ?>
                    </span>
                    <small class="fw-bold"><?= $dateFmt ?></small>
                  </div>
                  <h4 class="card-title mb-2 text-truncate-2"><?= htmlspecialchars($ev['event_title']) ?></h4>
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
                    data-body="<?= htmlspecialchars($ev['event_description'] ?? '') ?>"
                    aria-label="Read more about <?= htmlspecialchars($ev['event_title']) ?>">
                    Read More
                  </button>
                </div>
              </div>
            </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>

      <!-- Programs Column -->
      <div class="col-12 col-lg-6" id="programs">
        <h2 class="my-2 py-2">Community Health & Nutrition Programs</h2>
        <div class="row g-4 py-2">
          <div class="col-sm-12">
            <div class="program-box h-100 p-4 rounded-4 border bg-white">
              <h5>Child Immunization</h5>
              <p class="small text-secondary">
                Ensuring timely vaccination schedules to protect children from preventable diseases.
              </p>
              <a href="programs.php#immunization" class="small text-danger" aria-label="Learn more about child immunization">Learn More →</a>
            </div>
          </div>
          <div class="col-sm-12">
            <div class="program-box h-100 p-4 rounded-4 border bg-white">
              <h5>Nutrition Monitoring</h5>
              <p class="small text-secondary">
                Regular growth assessments, weight-for-length/height evaluations, and supplementation tracking.
              </p>
              <a href="programs.php#nutrition" class="small text-danger" aria-label="Learn more about nutrition monitoring">Learn More →</a>
            </div>
          </div>
          <div class="col-sm-12">
            <div class="program-box h-100 p-4 rounded-4 border bg-white">
              <h5>Maternal Health</h5>
              <p class="small text-secondary">
                Prenatal check-ups and risk screening to support safe and healthy pregnancies.
              </p>
              <a href="programs.php#maternal" class="small text-danger" aria-label="Learn more about maternal health">Learn More →</a>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<section id="about" class="py-5 section-alt" data-aos="fade-up">
  <div class="container my-5">
    <div class="row g-5 align-items-center">
      <div class="col-lg-6 about-text">
        <h2 class="my-4 py-2">About Barangay Sabang Health Initiative</h2>
        <p class="lead">
          A <span style="color:#dc3545;font-weight:600;">community-centered approach</span> to promoting health, preventing disease, and building resilient families.
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
            style="border:0;min-height:300px;" allowfullscreen="" loading="lazy"
            referrerpolicy="no-referrer-when-downgrade" title="Barangay Sabang Health Center Location"></iframe>
        </div>
      </div>
    </div>
  </div>
</section>

<section id="contact" class="py-5" data-aos="fade-up">
  <div class="container my-5">
    <div class="row g-5">
      <div class="col-lg-5">
        <h2 class="my-4 pb-3">Contact & Location</h2>
        <p class="small text-secondary">
          Reach out for schedules, health inquiries, or program participation.
        </p>
        <ul class="list-unstyled small">
          <li><i class="bi bi-telephone text-danger me-2" style="font-size:1.2rem;"></i> <a href="tel:+0123456789" class="text-decoration-none text-dark">Health Center: (012) 345-6789</a></li>
          <li><i class="bi bi-envelope text-danger me-2" style="font-size:1.2rem;"></i> <a href="mailto:health@sabang.gov" class="text-decoration-none text-dark">health@sabang.gov</a></li>
          <li><i class="bi bi-geo-alt text-danger me-2" style="font-size:1.2rem;"></i> Purok 1, Barangay Sabang</li>
        </ul>
        <div class="alert alert-warning small mt-4 mb-0">
          <strong>Reminder:</strong> Always bring your child’s immunization card during visits.
        </div>
      </div>
      <div class="col-lg-7">
        <div class="card border border-dark rounded-3 shadow-lg">
          <div class="card-body">
            <h5 class="mb-3">Feedback / Suggestion</h5>
            <form id="feedbackForm" novalidate>
              <div class="row g-3">
                <div class="col-md-6">
                  <label class="form-label small fw-semibold">Name</label>
                  <input type="text" class="form-control" required aria-describedby="nameError">
                  <div class="invalid-feedback" id="nameError">Please enter your name.</div>
                </div>
                <div class="col-md-6">
                  <label class="form-label small fw-semibold">Email</label>
                  <input type="email" class="form-control" required aria-describedby="emailError">
                  <div class="invalid-feedback" id="emailError">Please enter a valid email.</div>
                </div>
                <div class="col-12">
                  <label class="form-label small fw-semibold">Message</label>
                  <textarea class="form-control" rows="4" required aria-describedby="messageError"></textarea>
                  <div class="invalid-feedback" id="messageError">Please enter your message.</div>
                </div>
                <div class="col-12">
                  <button class="btn btn-danger px-4" type="submit" aria-label="Send feedback message">Send Message</button>
                  <small class="text-success ms-3 d-none" id="feedbackSuccess">Sent!</small>
                </div>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- Announcement Modal -->
<div class="modal fade" id="announcementModal" tabindex="-1" aria-labelledby="announcementModalLabel" role="dialog">
  <div class="modal-dialog modal-dialog-scrollable modal-lg modal-fullscreen-sm-down">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title announcement-modal-title" id="announcementModalLabel"></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="small text-secondary mb-2">
          <span class="announcement-modal-date"></span> •
          <span class="announcement-modal-time"></span>
        </div>
        <div class="announcement-modal-location small mb-3 text-muted d-none"></div>
        <div class="announcement-modal-body" style="font-size:1rem;line-height:1.7;"></div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-outline-primary" onclick="navigator.share ? navigator.share({title: document.querySelector('.announcement-modal-title').textContent, text: document.querySelector('.announcement-modal-body').textContent, url: window.location.href}) : alert('Share feature not supported');" aria-label="Share announcement">
          <i class="bi bi-share"></i> Share
        </button>
        <button class="btn btn-outline-secondary" data-bs-dismiss="modal" aria-label="Close modal">Close</button>
      </div>
    </div>
  </div>
</div>

<!-- Parent Quick Login Modal removed: use parent_login.php page -->

<?php require_once __DIR__.'/partials/footer.php'; ?>