/**
 * Pizzalog Embed Widget
 * Uso en arrabbiata.com.ar:
 *
 *   <div id="pizzalog-widget"></div>
 *   <script src="https://pizzalog.net/embed.js"></script>
 *
 * Opciones (atributos data- en el div):
 *   data-user="eze"        — usuario a mostrar (default: eze)
 *   data-posts="4"         — cantidad de posts (1-12, default: 4)
 *   data-theme="dark"      — dark | light | auto (default: dark)
 *   data-show-text="true"  — mostrar texto del post (default: false)
 *
 * Ejemplo con opciones:
 *   <div id="pizzalog-widget"
 *        data-user="eze"
 *        data-posts="4"
 *        data-theme="dark"
 *        data-show-text="true"></div>
 */

(function () {
  'use strict';

  var API_BASE   = 'https://pizzalog.net/api/feed.php';
  var SITE_URL   = 'https://pizzalog.net';
  var CONTAINER_ID = 'pizzalog-widget';

  var container = document.getElementById(CONTAINER_ID);
  if (!container) return;

  var username  = container.getAttribute('data-user')      || 'eze';
  var numPosts  = Math.min(12, Math.max(1, parseInt(container.getAttribute('data-posts') || '4', 10)));
  var theme     = container.getAttribute('data-theme')     || 'dark';
  var showText  = container.getAttribute('data-show-text') === 'true';

  // ── Estilos inline (no depende de hojas externas) ─────────────
  var isDark = theme === 'dark' || (theme === 'auto' && window.matchMedia('(prefers-color-scheme: dark)').matches);

  var styles = {
    wrapper: [
      'font-family:Verdana,Geneva,Tahoma,sans-serif',
      'font-size:12px',
      'line-height:1.4',
      isDark ? 'background:#0d2a2a' : 'background:#f5f5f0',
      isDark ? 'color:#ccdddd'      : 'color:#222',
      'border:1px solid ' + (isDark ? '#2a5555' : '#ddd'),
      'padding:12px',
      'max-width:480px',
    ].join(';'),

    header: [
      'display:flex',
      'align-items:center',
      'justify-content:space-between',
      'margin-bottom:10px',
      'padding-bottom:6px',
      'border-bottom:1px solid ' + (isDark ? '#2a5555' : '#ddd'),
    ].join(';'),

    title: [
      'font-size:13px',
      'font-weight:bold',
      isDark ? 'color:#ff44cc' : 'color:#c0392b',
      'text-decoration:none',
    ].join(';'),

    grid: [
      'display:grid',
      'grid-template-columns:repeat(' + Math.min(numPosts, 4) + ',1fr)',
      'gap:8px',
    ].join(';'),

    item: 'text-align:center',

    img: [
      'width:100%',
      'aspect-ratio:4/3',
      'object-fit:cover',
      'display:block',
      'border:1px solid ' + (isDark ? '#2a5555' : '#ddd'),
    ].join(';'),

    itemTitle: [
      'font-size:10px',
      'font-weight:bold',
      isDark ? 'color:#ff44cc' : 'color:#c0392b',
      'display:block',
      'margin-top:3px',
      'overflow:hidden',
      'text-overflow:ellipsis',
      'white-space:nowrap',
      'text-decoration:none',
    ].join(';'),

    date: [
      'font-size:9px',
      isDark ? 'color:#7aabab' : 'color:#888',
      'display:block',
    ].join(';'),

    body: [
      'font-size:10px',
      isDark ? 'color:#ccdddd' : 'color:#444',
      'margin-top:2px',
      'text-align:left',
      'display:block',
    ].join(';'),

    footer: [
      'margin-top:8px',
      'text-align:right',
      'font-size:10px',
      isDark ? 'color:#7aabab' : 'color:#888',
    ].join(';'),

    footerLink: [
      isDark ? 'color:#44ffee' : 'color:#c0392b',
      'text-decoration:none',
    ].join(';'),
  };

  // ── Render de carga ─────────────────────────────────────────────
  container.style.cssText = styles.wrapper;
  container.innerHTML = '<div style="text-align:center;padding:16px;' + (isDark ? 'color:#7aabab' : 'color:#888') + '">Cargando Pizzalog...</div>';

  // ── Fetch ────────────────────────────────────────────────────────
  var url = API_BASE + '?u=' + encodeURIComponent(username) + '&n=' + numPosts;

  fetch(url)
    .then(function (r) { return r.json(); })
    .then(function (data) {
      if (data.error) {
        container.innerHTML = '<p style="color:#ff4444;font-size:11px;">Error: ' + data.error + '</p>';
        return;
      }

      var posts = data.posts || [];
      if (!posts.length) {
        container.innerHTML = '<p style="font-size:11px;' + (isDark ? 'color:#7aabab' : 'color:#888') + '">Sin posts todavía.</p>';
        return;
      }

      // Header
      var header = '<div style="' + styles.header + '">'
        + '<a href="' + escHtml(data.profile_url) + '" target="_blank" style="' + styles.title + '">'
        + '🍕 ' + escHtml(data.display_name) + ' en Pizzalog'
        + '</a>'
        + '<a href="' + escHtml(data.profile_url) + '" target="_blank" style="font-size:10px;' + styles.footerLink + '">ver todo &rarr;</a>'
        + '</div>';

      // Grid de posts
      var cols = Math.min(posts.length, 4);
      var gridStyle = 'display:grid;grid-template-columns:repeat(' + cols + ',1fr);gap:8px;';
      var grid = '<div style="' + gridStyle + '">';
      posts.forEach(function (p) {
        grid += '<div style="' + styles.item + '">'
          + '<a href="' + escHtml(p.post_url) + '" target="_blank">'
          + '<img src="' + escHtml(p.thumb_url) + '" alt="' + escHtml(p.title) + '" style="' + styles.img + '" loading="lazy">'
          + '</a>'
          + '<a href="' + escHtml(p.post_url) + '" target="_blank" style="' + styles.itemTitle + '" title="' + escHtml(p.title) + '">' + escHtml(p.title) + '</a>'
          + '<span style="' + styles.date + '">' + escHtml(p.date_fmt) + '</span>';

        if (showText && p.body) {
          grid += '<span style="' + styles.body + '">' + escHtml(p.body.substring(0, 100)) + (p.body.length > 100 ? '...' : '') + '</span>';
        }

        grid += '</div>';
      });
      grid += '</div>';

      // Footer
      var footer = '<div style="' + styles.footer + '">'
        + 'Powered by <a href="' + SITE_URL + '" target="_blank" style="' + styles.footerLink + '">Pizzalog</a>'
        + '</div>';

      container.innerHTML = header + grid + footer;
    })
    .catch(function () {
      container.innerHTML = '<p style="color:#ff4444;font-size:11px;">No se pudo cargar el Pizzalog.</p>';
    });

  function escHtml(str) {
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }
})();
