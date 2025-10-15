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
    AND is_completed = 0
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
<script></script>
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
    <div class="row align-items-center g-5 pb-5 mb-5">
      <div class="col-lg-12">
        <h1 class="display-4 fw-bold mb-3 text-center text-white" style="color:#212529;">Welcome to Barangay Pamahalaan ng Barangay Sabang</h1>
        <h2 class="text-center text-white">Health & Nutrition Portal</h2>
        <p class="lead mb-4 px-5 text-white text-center">
          Stay informed about immunizations, nutrition programs, maternal care, and community health events.
        </p>
        <div class="d-flex justify-content-center gap-3 pb-5 mb-5">
          <a href="#updates" class="btn btn-danger btn-lg px-4" aria-label="View all announcements">View Announcements</a>
          <a href="#updates" class="btn btn-danger btn-lg px-4" aria-label="View community programs">Community Programs</a>
        </div>

      </div>
    </div>
  </div>
</section>

<!-- Our Services -->
<section id="services" class="py-5 section-alt" data-aos="fade-up">
  <div class="container my-5 pt-3">
    <div class="text-center mb-3">
      <h2 class="display-6 fw-bold" style="color:#dc3545;">Our Services</h2>
      <p class="text-muted">We offer a wide range of services to support the needs of our community members</p>
    </div>
    <div class="row g-4">
      <div class="col-md-6 col-lg-4">
        <div class="card h-100 shadow-lg border-0 p-4 rounded-4">
          <div class="d-flex align-items-center gap-3 mb-2">
            <div class="d-inline-flex align-items-center justify-content-center rounded-3" style="width:48px;height:48px;background:#fdecec;color:#dc3545;">
              <i class="bi bi-file-earmark-text"></i>
            </div>
            <h5 class="fw-semibold mb-0">Barangay Clearance</h5>
          </div>
          <p class="text-muted small mb-0">Fast and efficient processing of barangay clearances for various purposes.</p>
        </div>
      </div>
      <div class="col-md-6 col-lg-4">
        <div class="card h-100 shadow-lg border-0 p-4 rounded-4">
          <div class="d-flex align-items-center gap-3 mb-2">
            <div class="d-inline-flex align-items-center justify-content-center rounded-3" style="width:48px;height:48px;background:#fdecec;color:#dc3545;">
              <i class="bi bi-shield"></i>
            </div>
            <h5 class="fw-semibold mb-0">Peace &amp; Order</h5>
          </div>
          <p class="text-muted small mb-0">24/7 barangay security and assistance for maintaining community safety.</p>
        </div>
      </div>
      <div class="col-md-6 col-lg-4">
        <div class="card h-100 shadow-lg border-0 p-4 rounded-4">
          <div class="d-flex align-items-center gap-3 mb-2">
            <div class="d-inline-flex align-items-center justify-content-center rounded-3" style="width:48px;height:48px;background:#fdecec;color:#dc3545;">
              <i class="bi bi-people"></i>
            </div>
            <h5 class="fw-semibold mb-0">Community Programs</h5>
          </div>
          <p class="text-muted small mb-0">Regular activities promoting health, education, and social development.</p>
        </div>
      </div>
      <div class="col-md-6 col-lg-4">
        <div class="card h-100 shadow-lg border-0 p-4 rounded-4">
          <div class="d-flex align-items-center gap-3 mb-2">
            <div class="d-inline-flex align-items-center justify-content-center rounded-3" style="width:48px;height:48px;background:#fdecec;color:#dc3545;">
              <i class="bi bi-buildings"></i>
            </div>
            <h5 class="fw-semibold mb-0">Business Permits</h5>
          </div>
          <p class="text-muted small mb-0">Assistance in processing barangay business permits and certifications.</p>
        </div>
      </div>
      <div class="col-md-6 col-lg-4">
        <div class="card h-100 shadow-lg border-0 p-4 rounded-4">
          <div class="d-flex align-items-center gap-3 mb-2">
            <div class="d-inline-flex align-items-center justify-content-center rounded-3" style="width:48px;height:48px;background:#fdecec;color:#dc3545;">
              <i class="bi bi-bag"></i>
            </div>
            <h5 class="fw-semibold mb-0">Livelihood Programs</h5>
          </div>
          <p class="text-muted small mb-0">Skills training and support for community members seeking employment.</p>
        </div>
      </div>
      <div class="col-md-6 col-lg-4">
        <div class="card h-100 shadow-lg border-0 p-4 rounded-4">
          <div class="d-flex align-items-center gap-3 mb-2">
            <div class="d-inline-flex align-items-center justify-content-center rounded-3" style="width:48px;height:48px;background:#fdecec;color:#dc3545;">
              <i class="bi bi-heart"></i>
            </div>
            <h5 class="fw-semibold mb-0">Social Services</h5>
          </div>
          <p class="text-muted small mb-0">Assistance programs for indigent families and senior citizens.</p>
        </div>
      </div>
    </div>
  </div>
    <div class="container">
    <div class="text-center mb-5">
      <h2 class="display-6 fw-bold" style="color:#dc3545;">Get In Touch</h2>
      <p class="text-muted">Have questions or need assistance? Feel free to reach out to us</p>
    </div>
    <div class="row g-4">
      <div class="col-md-6 col-lg-3">
        <div class="card h-100 shadow-lg border-0 p-4 rounded-4">
          <div class="d-flex align-items-center gap-3 mb-2">
            <div class="d-inline-flex align-items-center justify-content-center rounded-3" style="width:48px;height:48px;background:#fdecec;color:#dc3545;">
              <i class="bi bi-geo-alt"></i>
            </div>
            <h6 class="fw-semibold mb-0">Address</h6>
          </div>
          <div class="text-muted small">
            Barangay Hall, Pawa Halaan<br>
            Municipality, Province<br>
            Philippines
          </div>
        </div>
      </div>
      <div class="col-md-6 col-lg-3">
        <div class="card h-100 shadow-lg border-0 p-4 rounded-4">
          <div class="d-flex align-items-center gap-3 mb-2">
            <div class="d-inline-flex align-items-center justify-content-center rounded-3" style="width:48px;height:48px;background:#fdecec;color:#dc3545;">
              <i class="bi bi-telephone"></i>
            </div>
            <h6 class="fw-semibold mb-0">Phone</h6>
          </div>
          <div class="text-muted small">
            (02) 1234-5678<br>
            Mobile: +63 912-345-6789
          </div>
        </div>
      </div>
      <div class="col-md-6 col-lg-3">
        <div class="card h-100 shadow-lg border-0 p-4 rounded-4">
          <div class="d-flex align-items-center gap-3 mb-2">
            <div class="d-inline-flex align-items-center justify-content-center rounded-3" style="width:48px;height:48px;background:#fdecec;color:#dc3545;">
              <i class="bi bi-envelope"></i>
            </div>
            <h6 class="fw-semibold mb-0">Email</h6>
          </div>
          <div class="text-muted small">
            barangay.pawahalaan@gov.ph<br>
            info@pawahalaan.gov.ph
          </div>
        </div>
      </div>
      <div class="col-md-6 col-lg-3">
        <div class="card h-100 shadow-lg border-0 p-4 rounded-4">
          <div class="d-flex align-items-center gap-3 mb-2">
            <div class="d-inline-flex align-items-center justify-content-center rounded-3" style="width:48px;height:48px;background:#fdecec;color:#dc3545;">
              <i class="bi bi-clock"></i>
            </div>
            <h6 class="fw-semibold mb-0">Office Hours</h6>
          </div>
          <div class="text-muted small">
            Monday - Friday: 8:00 AM - 5:00 PM<br>
            Saturday: 8:00 AM - 12:00 PM<br>
            Sunday: Closed
          </div>
        </div>
      </div>
    </div>
  </div>
  <style>
    #contact-cards .card{border:1px solid #edf0f2;}
  </style>
  <style>
    #services .card{border:1px solid #edf0f2;}
    #services .card:hover{transform:translateY(-2px);transition:.2s;box-shadow:0 6px 18px rgba(0,0,0,.06);}
  </style>
  </section>

<!-- Barangay Officials -->
<section id="officials" class="py-5 section-alt" data-aos="fade-up">
  <div class="container my-5 pt-5">
    <div class="text-center mb-5">
      <h2 class="display-6 fw-bold" style="color:#dc3545;">Barangay Officials</h2>
      <p class="text-muted">Meet the dedicated leaders working tirelessly to serve our community</p>
    </div>
    <div class="row g-4 fs-5">
      <?php
        $officials = [
          ['name'=>'Hon. Maria Santos','title'=>'Barangay Captain','initials'=>'MS'],
          ['name'=>'Hon. Juan Dela Cruz','title'=>'Barangay Kagawad','initials'=>'JD'],
          ['name'=>'Hon. Ana Reyes','title'=>'Barangay Kagawad','initials'=>'AR'],
          ['name'=>'Hon. Roberto Garcia','title'=>'Barangay Kagawad','initials'=>'RG'],
          ['name'=>'Hon. Elena Rodriguez','title'=>'Barangay Kagawad','initials'=>'ER'],
          ['name'=>'Hon. Carlos Mendoza','title'=>'Barangay Kagawad','initials'=>'CM'],
          ['name'=>'Hon. Patricia Cruz','title'=>'SK Chairperson','initials'=>'PC'],
          ['name'=>'Jose Martinez','title'=>'Barangay Secretary','initials'=>'JM'],
        ];
        foreach($officials as $o): ?>
        <div class="col-6 col-md-4 col-lg-3">
          <div class="card h-100 shadow-lg border-0 p-4 text-center rounded-4">
            <div class="mx-auto rounded-circle d-flex align-items-center justify-content-center mb-3" style="width:88px;height:88px;background:#dc3545;color:#fff;border:6px solid #ffe3e3;font-weight:800;">
              <?= htmlspecialchars($o['initials']) ?>
            </div>
            <h6 class="fw-bold mb-1" style="min-height:40px;"><?= htmlspecialchars($o['name']) ?></h6>
            <div class="text-muted small"><?= htmlspecialchars($o['title']) ?></div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
  <style>
    #officials .card{border:1px solid #edf0f2;}
  </style>
</section>

<section id="updates" class="py-5 section-alt" data-aos="fade-up">
  <div class="container">
    <div class="row g-5 align-items-start mt-3">
      <!-- Announcements Column -->
      <div class="col-12 col-lg-6" id="announcements">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 my-2 py-2">
          <h2 class="display-6 fw-bold" style="color:#dc3545;">Announcements & Events</h2>
          <p class="text-muted">Stay updated with the latest announcements and events in our community.</p>
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
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 my-2 py-2">
        <h2 class="display-6 fw-bold" style="color:#dc3545;">Health & Nutrition Programs</h2>
        <p class="text-muted">Explore our programs aimed at improving community health and nutrition.</p>
        <div class="row g-4 py-2">
          <div class="col-sm-12">
            <div class="program-box h-100 p-4 rounded-4 border bg-white shadow-lg">
              <h5>Child Immunization</h5>
              <p class="small text-secondary">
                Ensuring timely vaccination schedules to protect children from preventable diseases.
              </p>
              <a href="programs#immunization" class="small text-danger text-decoration-none" aria-label="Learn more about child immunization">Learn More →</a>
            </div>
          </div>
          <div class="col-sm-12">
            <div class="program-box h-100 p-4 rounded-4 border bg-white shadow-lg">
              <h5>Nutrition Monitoring</h5>
              <p class="small text-secondary">
                Regular growth assessments, weight-for-length/height evaluations, and supplementation tracking.
              </p>
              <a href="programs#nutrition" class="small text-danger text-decoration-none" aria-label="Learn more about nutrition monitoring">Learn More →</a>
            </div>
          </div>
          <div class="col-sm-12">
            <div class="program-box h-100 p-4 rounded-4 border bg-white shadow-lg">
              <h5>Maternal Health</h5>
              <p class="small text-secondary">
                Prenatal check-ups and risk screening to support safe and healthy pregnancies.
              </p>
              <a href="programs#maternal" class="small text-danger text-decoration-none" aria-label="Learn more about maternal health">Learn More →</a>
            </div>
          </div>
        </div>
      </div>
      </div>
    </div>
  </div>
</section>

<section id="about" class="py-5 section-alt" data-aos="fade-up">
  <div class="container my-5 pt-5">
    <div class="row g-5 align-items-center">
      <div class="col-lg-6 about-text">
       <h2 class="display-6 fw-bold" style="color:#dc3545;">About Barangay Sabang <br>Health & Nutrition Initiative</h2>
        <p class="lead">
          A <span style="color:#dc3545;font-weight:600;">community-centered approach</span> to promoting health, preventing disease, and building resilient families.
        </p>
        <p class="fs-6">
          This portal serves as a trusted information hub. Through collaboration among <span style="color:#dc3545;font-weight:600;"> Admin, Barangay Health Workers (BHW),
          and Barangay Nutrition Scholars (BNS), </span>we support families with accurate data and timely interventions.
        </p>
        <ul class="list-unstyled small">
          <li class="mb-2 fs-5"><i class="bi bi-check2-circle text-success me-1"></i> Data-driven immunization tracking</li>
          <li class="mb-2 fs-5"><i class="bi bi-check2-circle text-success me-1"></i> Nutrition and growth surveillance</li>
          <li class="mb-2 fs-5"><i class="bi bi-check2-circle text-success me-1"></i> Maternal and child health education</li>
        </ul>
      </div>
      <div class="col-lg-6">
        <div class="ratio ratio-16x9 rounded-4 overflow-hidden shadow-lg border border-5">
          <iframe
            src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3550.8190585948946!2d121.16785557339493!3d13.946972827182208!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x33bd6b62894b2ff7%3A0x858154ec3465aece!2sSabang%2C%20Lipa%20City%2C%20Batangas!5e0!3m2!1sen!2sph!4v1759315532961!5m2!1sen!2sph"
            style="border:0;min-height:300px;" allowfullscreen="" loading="lazy"
            referrerpolicy="no-referrer-when-downgrade" title="Barangay Sabang Health Center Location"></iframe>
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