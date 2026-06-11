<?php // includes/footer.php ?>
</main><!-- /.main-content -->

<footer class="site-footer">
  <div class="footer-inner">
    <span class="footer-brand"><span class="brand-dot"></span>PeerTutor</span>
    <span class="footer-copy">University Peer Tutoring Platform</span>
  </div>
</footer>

<?php if (isset($extra_js)): ?>
<script src="/peer-tutoring/assets/js/<?= $extra_js ?>"></script>
<?php endif; ?>
</body>
</html>
