<?php
require_once __DIR__.'/../../inc/db.php';
require_once __DIR__.'/../../inc/auth.php';
require_role(['Admin']);
?>
<div class="card border-0 shadow-sm">
  <div class="card-body">
    <h5 class="mb-3">Event Scheduling</h5>
    <div class="alert alert-info small mb-3">
      Placeholder. Implement a form to create new events (fields: title, type, date, time, location, description, is_published).
    </div>
    <form class="small">
      <div class="row g-3">
        <div class="col-md-4">
          <label class="form-label small fw-semibold">Title</label>
          <input type="text" class="form-control form-control-sm" disabled>
        </div>
        <div class="col-md-2">
          <label class="form-label small fw-semibold">Type</label>
          <select class="form-select form-select-sm" disabled>
            <option>health</option>
            <option>nutrition</option>
            <option>vaccination</option>
            <option>feeding</option>
            <option>general</option>
          </select>
        </div>
        <div class="col-md-2">
          <label class="form-label small fw-semibold">Date</label>
          <input type="date" class="form-control form-control-sm" disabled>
        </div>
        <div class="col-md-2">
          <label class="form-label small fw-semibold">Time</label>
          <input type="time" class="form-control form-control-sm" disabled>
        </div>
        <div class="col-md-2">
          <label class="form-label small fw-semibold">Publish?</label>
          <select class="form-select form-select-sm" disabled>
            <option value="1">Yes</option>
            <option value="0">No</option>
          </select>
        </div>
        <div class="col-12">
          <label class="form-label small fw-semibold">Location</label>
          <input type="text" class="form-control form-control-sm" disabled>
        </div>
        <div class="col-12">
          <label class="form-label small fw-semibold">Description</label>
          <textarea class="form-control form-control-sm" rows="3" disabled></textarea>
        </div>
        <div class="col-12">
          <button class="btn btn-primary btn-sm" type="button" disabled>Create Event</button>
        </div>
      </div>
    </form>
  </div>
</div>