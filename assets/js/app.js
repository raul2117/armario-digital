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

// Mensajes tras guardar/borrar
if (window.FLASH && window.FLASH.clave) {
  // Contraseña temporal: se muestra una sola vez para copiarla
  Swal.fire({
    icon: 'success',
    title: window.FLASH.mensaje,
    html: 'Contraseña temporal (solo se muestra ahora):<br><code style="display:inline-block;margin-top:10px;padding:10px 16px;border-radius:12px;background:#F5F3FF;font-size:22px;letter-spacing:1px;user-select:all"></code><br><small>Al entrar tendrá que cambiarla.</small>',
    confirmButtonText: 'Copiar y cerrar',
    ...colores,
    didOpen: el => { el.querySelector('code').textContent = window.FLASH.clave; }
  }).then(() => { if (navigator.clipboard) navigator.clipboard.writeText(window.FLASH.clave).catch(() => {}); });
} else if (window.FLASH) {
  Swal.fire({
    icon: window.FLASH.tipo,
    title: window.FLASH.mensaje,
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
      title: form.dataset.confirmar,
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
      Swal.fire({ icon: 'info', title: 'Elige al menos dos prendas', text: 'Toca las prendas de la izquierda para montar el outfit.', ...colores });
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
