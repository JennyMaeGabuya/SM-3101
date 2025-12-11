<?php
?>
    </main>
  </div>
  <footer class="th-footer" style="padding:18px; text-align:center; border-top:1px solid var(--border-color); background:var(--bg-secondary); color:var(--text-secondary);">
    &copy; <?php echo date('Y'); ?> LearnHub
  </footer>
  <script src="assets/js/dashboard.js"></script>
  <!-- fallback absolute path in case relative path fails -->
  <script>if(!document.querySelector('[src="assets/js/dashboard.js"]') || typeof window === 'undefined'){}</script>
  <script src="/LearnHub/teacher/assets/js/dashboard.js"></script>
  <!-- Inline fallback: ensure the CSS 'page-ready' state is applied if dashboard.js doesn't run -->
  <script>
    try {
      if (!document.documentElement.classList.contains('page-ready')) {
        document.documentElement.classList.add('page-ready');
      }
    } catch (e) { /* ignore */ }
  </script>
  <script>
    // Fallback: ensure main content is visible even if dashboard JS fails.
    try {
      document.documentElement.classList.add('page-ready');
    } catch (e) {
      /* ignore */
    }
  </script>
  <script>
    // Minimal JS for responsive sidebar toggle
    document.addEventListener('click', function(e){
      if (e.target && e.target.matches('.th-sidebar-toggle')){
        document.querySelector('.th-layout').classList.toggle('collapsed');
      }
    });
  </script>
</body>
</html>
