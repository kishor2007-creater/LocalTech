// Confirm destructive actions
document.addEventListener('submit', e => { const m = e.target.dataset.confirm; if (m && !confirm(m)) e.preventDefault(); });
// Auto-hide toast
const t = document.querySelector('.toast'); if (t) setTimeout(() => { t.style.display = 'none'; }, 5000);
// Geolocation: sort search results by distance
const lb = document.getElementById('locateBtn');
if (lb) lb.addEventListener('click', () => {
  if (!navigator.geolocation) return alert('Location is not supported by this browser.');
  lb.textContent = 'Locating…';
  navigator.geolocation.getCurrentPosition(p => {
    document.getElementById('lat').value = p.coords.latitude; document.getElementById('lng').value = p.coords.longitude;
    const f = document.getElementById('searchForm'); f.insertAdjacentHTML('beforeend', '<input type="hidden" name="sort" value="near">'); f.submit();
  }, () => { lb.textContent = 'Use my location'; alert('Location permission was denied. You can still filter by city.'); });
});
// Registration: show shop fields + pin location
const reg = document.getElementById('regForm');
if (reg) {
  const sf = document.getElementById('shopFields');
  reg.querySelectorAll('[name=role]').forEach(r => r.addEventListener('change', () => { sf.hidden = reg.role.value !== 'shop'; }));
  document.getElementById('shopLocate').addEventListener('click', ev => navigator.geolocation && navigator.geolocation.getCurrentPosition(p => {
    document.getElementById('slat').value = p.coords.latitude; document.getElementById('slng').value = p.coords.longitude; ev.target.textContent = 'Location pinned ✓';
  }));
}
