document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('[data-carousel]').forEach(function (wrap) {
    var track = wrap.querySelector('[data-carousel-track]');
    if (!track) return;
    var prev = wrap.querySelector('[data-carousel-prev]');
    var next = wrap.querySelector('[data-carousel-next]');

    var scrollByAmount = function () {
      return Math.max(220, track.clientWidth * 0.6);
    };

    var updateButtons = function () {
      if (!prev || !next) return;
      var maxScroll = track.scrollWidth - track.clientWidth - 1;
      prev.disabled = track.scrollLeft <= 0;
      next.disabled = track.scrollLeft >= maxScroll;
    };

    if (prev) {
      prev.addEventListener('click', function () {
        track.scrollBy({ left: -scrollByAmount(), behavior: 'smooth' });
      });
    }
    if (next) {
      next.addEventListener('click', function () {
        track.scrollBy({ left: scrollByAmount(), behavior: 'smooth' });
      });
    }

    track.addEventListener('scroll', function () {
      updateButtons();
    });

    updateButtons();
  });

  document.querySelectorAll('[data-anime-directory]').forEach(function (dir) {
    var grid = dir.querySelector('[data-anime-grid]');
    var loadBtn = dir.querySelector('[data-anime-load]');
    if (!grid || !loadBtn) return;

    var base = dir.getAttribute('data-base-url') || '';
    var query = dir.getAttribute('data-query') || '';
    var nextPage = parseInt(dir.getAttribute('data-next-page') || '2', 10);
    var totalPages = parseInt(dir.getAttribute('data-total-pages') || '1', 10);
    var loading = false;

    var buildUrl = function (page) {
      var qs = query ? (query + '&') : '';
      qs += 'paged=' + page;
      return base + (qs ? ('?' + qs) : '');
    };

    var updateBtn = function () {
      if (!loadBtn) return;
      if (nextPage > totalPages) {
        loadBtn.disabled = true;
        loadBtn.textContent = 'No more results';
      }
    };

    var appendFromHtml = function (html) {
      var parser = new DOMParser();
      var doc = parser.parseFromString(html, 'text/html');
      var newGrid = doc.querySelector('[data-anime-grid]');
      if (!newGrid) return false;
      var items = Array.prototype.slice.call(newGrid.children);
      if (!items.length) return false;
      items.forEach(function (node) {
        grid.appendChild(node);
      });
      return true;
    };

    var loadNext = function () {
      if (loading || nextPage > totalPages) return;
      loading = true;
      loadBtn.textContent = 'Loading...';
      fetch(buildUrl(nextPage), { credentials: 'same-origin' })
        .then(function (res) { return res.text(); })
        .then(function (html) {
          var appended = appendFromHtml(html);
          if (!appended) {
            nextPage = totalPages + 1;
          } else {
            nextPage += 1;
          }
          updateBtn();
        })
        .catch(function () {
          loadBtn.textContent = 'Try again';
        })
        .finally(function () {
          loading = false;
          if (!loadBtn.disabled) {
            loadBtn.textContent = 'Load more';
          }
        });
    };

    loadBtn.addEventListener('click', loadNext);

    if ('IntersectionObserver' in window) {
      var io = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
          if (entry.isIntersecting) loadNext();
        });
      }, { rootMargin: '240px' });
      io.observe(loadBtn);
    }

    updateBtn();
  });
});
