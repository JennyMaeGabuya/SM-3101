// Shared runtime settings for the app (single source of truth)
(function() {
  // Undo timeout when an account is deleted (ms)
  // You can change this value once here and it will apply across pages.
  window.DELETE_UNDO_TIMEOUT_MS = 8000; // 8 seconds
  // also expose an object for future settings
  window.APP_SETTINGS = window.APP_SETTINGS || {}
  window.APP_SETTINGS.deleteUndoTimeoutMs = window.DELETE_UNDO_TIMEOUT_MS
})();
