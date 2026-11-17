// Armario digital — comportamiento común
const CSRF = document.querySelector('meta[name="csrf"]').content;
// Colores de SweetAlert según el tema activo (se leen de las variables CSS)
const css = n => getComputedStyle(document.documentElement).getPropertyValue(n).trim();
const colores = {
  confirmButtonColor: '#5B3FD9',
  cancelButtonColor: '#6E6889',
  get background() { return css('--superficie'); },
  get color() { return css('--tinta'); }
};

// Mensajes tras guardar/borrar. Llegan como JSON en <script type="application/json" id="flash">
// (no se ejecuta, así la política CSP puede prohibir todo JavaScript en línea).
// IMPORTANTE: los textos con datos del usuario van siempre en titleText/text, nunca en title/html,
// porque SweetAlert2 interpreta title y html como HTML.
let FLASH = null;
try {
  const datosFlash = document.getElementById('flash');
  if (datosFlash) FLASH = JSON.parse(datosFlash.textContent);
} catch (e) { FLASH = null; }

if (FLASH && FLASH.clave) {
  // Contraseña temporal: se muestra una sola vez para copiarla
  Swal.fire({
    icon: 'success',
    titleText: FLASH.mensaje,
    html: 'Contraseña temporal (solo se muestra ahora):<br><code style="display:inline-block;margin-top:10px;padding:10px 16px;border-radius:12px;background:#F5F3FF;font-size:22px;letter-spacing:1px;user-select:all"></code><br><small>Al entrar tendrá que cambiarla.</small>',
    confirmButtonText: 'Copiar y cerrar',
    ...colores,
    didOpen: el => { el.querySelector('code').textContent = FLASH.clave; }
  }).then(() => { if (navigator.clipboard) navigator.clipboard.writeText(FLASH.clave).catch(() => {}); });
} else if (FLASH) {
  Swal.fire({
    icon: ['success', 'error', 'warning', 'info', 'question'].includes(FLASH.tipo) ? FLASH.tipo : 'info',
    titleText: FLASH.mensaje,
    timer: 2200,
    showConfirmButton: false,
    toast: true,
    position: 'top-end',
    ...colores
  });
}

// Confirmación antes de enviar formularios peligrosos: <form data-confirmar="¿Seguro?">
document.querySelectorAll('form[data-confirmar]').forEach(form => {
  form.addEventListener('submit', e => {
    if (form.dataset.ok) return;
    e.preventDefault();
    Swal.fire({
      titleText: form.dataset.confirmar,
      text: form.dataset.texto || '',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: form.dataset.boton || 'Sí, borrar',
      cancelButtonText: 'Cancelar',
      ...colores
    }).then(r => {
      if (r.isConfirmed) { form.dataset.ok = '1'; form.submit(); }
    });
  });
});

// Favoritas
document.querySelectorAll('.fav').forEach(btn => {
  btn.addEventListener('click', async () => {
    const res = await fetch('api/favorita.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF': CSRF },
      body: 'id=' + encodeURIComponent(btn.dataset.id)
    });
    if (!res.ok) return;
    const data = await res.json();
    btn.classList.toggle('on', data.favorita);
    btn.setAttribute('aria-pressed', data.favorita ? 'true' : 'false');
  });
});

// Vista previa de la foto al subir una prenda
const inputFoto = document.querySelector('#foto');
if (inputFoto) {
  inputFoto.addEventListener('change', () => {
    const f = inputFoto.files[0];
    if (!f) return;
    const caja = inputFoto.closest('.subir');
    let img = caja.querySelector('img');
    if (!img) { img = document.createElement('img'); img.alt = ''; caja.appendChild(img); }
    img.src = URL.createObjectURL(f);
  });
}

// Creador de outfits: al tocar una prenda se coloca en su hueco
document.querySelectorAll('.pool-item').forEach(btn => {
  btn.addEventListener('click', () => {
    const hueco = document.querySelector('.hueco[data-hueco="' + btn.dataset.hueco + '"]');
    if (!hueco) return;
    hueco.querySelector('input').value = btn.dataset.id;
    hueco.querySelector('.hueco-contenido').innerHTML =
      btn.querySelector('.tile').outerHTML + '<span class="nombre"></span>';
    hueco.querySelector('.nombre').textContent = btn.dataset.nombre;
  });
});
document.querySelectorAll('.hueco').forEach(h => {
  h.addEventListener('click', () => {
    if (!h.querySelector('input').value) return;
    h.querySelector('input').value = '';
    h.querySelector('.hueco-contenido').innerHTML =
      '<span class="hueco-vacio"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg></span>';
  });
});
const vaciar = document.querySelector('#vaciar');
if (vaciar) vaciar.addEventListener('click', () => document.querySelectorAll('.hueco').forEach(h => h.click()));

const formOutfit = document.querySelector('#form-outfit');
if (formOutfit) {
  formOutfit.addEventListener('submit', e => {
    const elegidas = [...formOutfit.querySelectorAll('.hueco input')].filter(i => i.value);
    if (elegidas.length < 2) {
      e.preventDefault();
      Swal.fire({ icon: 'info', titleText: 'Elige al menos dos prendas', text: 'Toca las prendas de la izquierda para montar el outfit.', ...colores });
    }
  });
}

// Cerrar el menú de perfil al pulsar fuera o con Escape
const perfil = document.querySelector('.perfil');
if (perfil) {
  document.addEventListener('click', e => { if (perfil.open && !perfil.contains(e.target)) perfil.open = false; });
  document.addEventListener('keydown', e => { if (e.key === 'Escape' && perfil.open) { perfil.open = false; perfil.querySelector('summary').focus(); } });
}

// Tema claro / oscuro / automático
document.querySelectorAll('.tema-selector button').forEach(btn => {
  btn.addEventListener('click', () => {
    const tema = btn.dataset.tema;
    document.documentElement.dataset.tema = tema;
    document.querySelectorAll('.tema-selector button').forEach(b => b.setAttribute('aria-pressed', b.dataset.tema === tema ? 'true' : 'false'));
    document.cookie = 'tema=' + tema + '; max-age=31536000; path=/; samesite=lax';
    // Con sesión iniciada se guarda también en la cuenta (así se aplica en todos sus dispositivos)
    if (!document.body.classList.contains('publico')) {
      fetch('api/tema.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF': CSRF },
        body: 'tema=' + encodeURIComponent(tema)
      }).catch(() => {});
    }
  });
});

// Ubicación del dispositivo (ubicacion.php). La geolocalización del navegador solo está permitida en https.
const cajaGeo = document.getElementById('geo');
if (cajaGeo) {
  const ok = window.isSecureContext && 'geolocation' in navigator;
  document.getElementById(ok ? 'geo' : 'geo-no').hidden = false;
  const btnGeo = document.getElementById('btn-geo');
  if (ok && btnGeo) {
    btnGeo.addEventListener('click', () => {
      btnGeo.disabled = true;
      navigator.geolocation.getCurrentPosition(pos => {
        document.getElementById('geo-lat').value = pos.coords.latitude.toFixed(5);
        document.getElementById('geo-lon').value = pos.coords.longitude.toFixed(5);
        document.getElementById('form-geo').submit();
      }, () => {
        btnGeo.disabled = false;
        Swal.fire({ icon: 'info', titleText: 'No se pudo obtener tu ubicación', text: 'Revisa el permiso del navegador o búscala por nombre.', ...colores });
      }, { timeout: 10000, maximumAge: 600000 });
    });
  }
}
