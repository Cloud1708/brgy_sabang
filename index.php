<?php
require_once __DIR__.'/inc/db.php';
require_once __DIR__.'/partials/header.php';

/*
 Fetch published events (announcements)
 Display:
  - Upcoming (event_date >= today)
  - Recent past (last 30 days)
 Limit for hero slider maybe 5 recent/next events
*/

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
<section class="hero d-flex align-items-center">
  <div class="container">
    <div class="row align-items-center g-5">
      <div class="col-lg-6">
        <h1 class="display-5 fw-bold mb-3 gradient-text">Barangay Health & Nutrition Portal</h1>
        <p class="lead mb-4">
          Stay informed about immunizations, nutrition programs, maternal care, and community health events.
        </p>
        <div class="d-flex flex-wrap gap-3">
          <a href="#announcements" class="btn btn-primary btn-lg px-4">
            View Announcements
          </a>
          <a href="#programs" class="btn btn-outline-primary btn-lg px-4">
            Community Programs
          </a>
        </div>
        <div class="stats-row mt-5 row g-3">
          <div class="col-4">
            <div class="stat-box text-center">
              <div class="stat-value" id="statImmunizations">—</div>
              <div class="stat-label">Vaccines Tracked</div>
            </div>
          </div>
          <div class="col-4">
            <div class="stat-box text-center">
              <div class="stat-value" id="statMothers">—</div>
              <div class="stat-label">Registered Mothers</div>
            </div>
          </div>
            <div class="col-4">
            <div class="stat-box text-center">
              <div class="stat-value" id="statChildren">—</div>
              <div class="stat-label">Children Monitored</div>
            </div>
          </div>
        </div>
      </div>
      <div class="col-lg-6 hero-visual">
        <div class="card shadow border-0 announcement-slider">
          <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <span class="fw-semibold">Highlighted Events</span>
            <span class="badge rounded-pill text-bg-primary">Health & Nutrition</span>
          </div>
          <div class="card-body p-0">
            <?php if (count($events) === 0): ?>
              <div class="p-4 text-center small opacity-75">No announcements available.</div>
            <?php else: ?>
              <div id="highlightCarousel" class="carousel slide" data-bs-ride="carousel">
                <div class="carousel-inner">
                  <?php foreach ($events as $i => $ev): 
                    $dateFmt = date('M d, Y', strtotime($ev['event_date']));
                    $ex = mb_strimwidth(strip_tags($ev['event_description'] ?? ''), 0, 140, '...');
                  ?>
                  <div class="carousel-item <?php echo $i===0 ? 'active' : ''; ?>">
                    <div class="p-4">
                      <h5 class="mb-1"><?php echo htmlspecialchars($ev['event_title']); ?></h5>
                      <p class="small text-secondary mb-2">
                        <span class="me-2">
                          <i class="bi bi-calendar-event"></i> <?php echo $dateFmt; ?>
                        </span>
                        <?php if (!empty($ev['event_time'])): ?>
                          <span><i class="bi bi-clock"></i> <?php echo date('h:i A', strtotime($ev['event_time'])); ?></span>
                        <?php endif; ?>
                      </p>
                      <p class="small mb-2"><?php echo htmlspecialchars($ex); ?></p>
                      <?php if (!empty($ev['location'])): ?>
                        <div class="small text-muted"><i class="bi bi-geo-alt"></i> <?php echo htmlspecialchars($ev['location']); ?></div>
                      <?php endif; ?>
                    </div>
                  </div>
                  <?php endforeach; ?>
                </div>
                <?php if (count($events) > 1): ?>
                <button class="carousel-control-prev" type="button" data-bs-target="#highlightCarousel" data-bs-slide="prev">
                  <span class="carousel-control-prev-icon"></span>
                </button>
                <button class="carousel-control-next" type="button" data-bs-target="#highlightCarousel" data-bs-slide="next">
                  <span class="carousel-control-next-icon"></span>
                </button>
                <?php endif; ?>
              </div>
            <?php endif; ?>
          </div>
          <div class="card-footer bg-light small text-end">
            <a href="#announcements" class="text-decoration-none">See all announcements →</a>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<section id="announcements" class="py-5 section-alt">
  <div class="container">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
      <h2 class="h3 mb-0">Announcements & Events</h2>
      <div class="filter-controls">
        <button class="btn btn-sm btn-outline-secondary active" data-filter="all">All</button>
        <button class="btn btn-sm btn-outline-secondary" data-filter="health">Health</button>
        <button class="btn btn-sm btn-outline-secondary" data-filter="nutrition">Nutrition</button>
        <button class="btn btn-sm btn-outline-secondary" data-filter="vaccination">Vaccination</button>
        <button class="btn btn-sm btn-outline-secondary" data-filter="feeding">Feeding</button>
      </div>
    </div>
    <div class="row g-4" id="announcementList">
      <?php foreach ($events as $ev): 
        $type = htmlspecialchars($ev['event_type']);
        $dateFmt = date('M d, Y', strtotime($ev['event_date']));
      ?>
      <div class="col-md-6 col-lg-4 announcement-card" data-type="<?php echo $type; ?>">
        <div class="card h-100 shadow-sm border-0">
          <div class="card-body d-flex flex-column">
            <div class="d-flex justify-content-between align-items-start mb-2">
              <span class="badge text-bg-<?php echo match($ev['event_type']) {
                'health' => 'primary',
                'nutrition' => 'success',
                'vaccination' => 'warning',
                'feeding' => 'info',
                default => 'secondary'
              }; ?>">
                <?php echo ucfirst($ev['event_type']); ?>
              </span>
              <small class="text-muted"><?php echo $dateFmt; ?></small>
            </div>
            <h5 class="card-title mb-2"><?php echo htmlspecialchars($ev['event_title']); ?></h5>
            <p class="card-text small flex-grow-1">
              <?php echo htmlspecialchars(mb_strimwidth(strip_tags($ev['event_description'] ?? ''), 0, 160, '...')); ?>
            </p>
            <?php if (!empty($ev['location'])): ?>
              <div class="small text-muted mb-2">
                <i class="bi bi-geo-alt"></i> <?php echo htmlspecialchars($ev['location']); ?>
              </div>
            <?php endif; ?>
            <button class="btn btn-outline-primary btn-sm mt-auto view-announcement"
              data-title="<?php echo htmlspecialchars($ev['event_title']); ?>"
              data-date="<?php echo $dateFmt; ?>"
              data-time="<?php echo !empty($ev['event_time']) ? date('h:i A', strtotime($ev['event_time'])) : '—'; ?>"
              data-location="<?php echo htmlspecialchars($ev['location'] ?? ''); ?>"
              data-body="<?php echo htmlspecialchars($ev['event_description'] ?? ''); ?>">
              Read More
            </button>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
      <?php if (count($events) === 0): ?>
        <div class="col-12">
          <div class="alert alert-info">No announcements posted yet.</div>
        </div>
      <?php endif; ?>
    </div>
  </div>
</section>

<section id="programs" class="py-5">
  <div class="container">
    <h2 class="h3 mb-4">Community Health & Nutrition Programs</h2>
    <div class="row g-4">
      <div class="col-md-4">
        <div class="program-box h-100 p-4 rounded-4 border bg-white">
          <div class="icon-circle mb-3 bg-primary-subtle text-primary">
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
        <h2 class="h3 mb-3">About Barangay Sabang Health Initiative</h2>
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
            src="https://www.youtube.com/embed/7d7b8hBfB7E?rel=0"
            title="Health Education"
            allowfullscreen
            loading="lazy"></iframe>
        </div>
      </div>
    </div>
  </div>
</section>

<section id="contact" class="py-5">
  <div class="container">
    <div class="row g-5">
      <div class="col-lg-5">
        <h2 class="h3 mb-3">Contact & Location</h2>
        <p class="small text-secondary">
          Reach out for schedules, health inquiries, or program participation.
        </p>
        <ul class="list-unstyled small">
          <li><i class="bi bi-telephone text-primary me-2"></i> Health Center: (012) 345-6789</li>
          <li><i class="bi bi-envelope text-primary me-2"></i> health@sabang.gov</li>
          <li><i class="bi bi-geo-alt text-primary me-2"></i> Purok 1, Barangay Sabang</li>
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
                  <button class="btn btn-primary px-4" type="submit">Send Message</button>
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

<!-- Modal for Announcement -->
<div class="modal fade" id="announcementModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-scrollable modal-lg modal-fullscreen-sm-down">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title announcement-modal-title"></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" ></button>
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

<?php require_once __DIR__.'/partials/footer.php'; ?>