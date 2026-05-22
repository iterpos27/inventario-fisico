const $ = (id) => document.getElementById(id);
const baseUrl = window.BASE_URL || '';
const userRole = window.USER_ROLE || 'usuario';
const state = {
  items: new Map(),
  saving: false,
  created: $('conteoCreado')?.value === '1',
};

for (const item of window.CONTEO_INICIAL || []) {
  state.items.set(String(item.producto_id), {
    producto_id: Number(item.producto_id),
    codigo: item.codigo,
    descripcion: item.descripcion,
    cantidad: Number(item.cantidad),
  });
}

function showMessage(message, type = 'success') {
  const box = $('mensajeEstado');
  box.textContent = message;
  box.className = `save-message alert alert-${type}`;
  setTimeout(() => box.classList.add('d-none'), 3500);
}

function renderList() {
  const list = $('listaProductos');
  const empty = $('listaVacia');
  const items = Array.from(state.items.values());
  $('contadorLineas').textContent = items.length;
  empty.classList.toggle('d-none', items.length > 0);
  list.innerHTML = '';

  for (const item of items) {
    const row = document.createElement('div');
    row.className = 'count-item';
    row.innerHTML = `
      <div class="count-item-main">
        <span class="product-code">${escapeHtml(item.codigo)}</span>
        <strong>${escapeHtml(item.descripcion)}</strong>
      </div>
      <div class="count-item-actions">
        <input class="form-control" type="number" step="0.01" min="0" inputmode="decimal" value="${item.cantidad || ''}" placeholder="Cantidad" data-edit="${item.producto_id}">
        <button class="btn btn-outline-danger" type="button" data-delete="${item.producto_id}" aria-label="Eliminar"><i class="bi bi-trash"></i></button>
      </div>
    `;
    list.appendChild(row);
  }
}

function escapeHtml(value) {
  return String(value).replace(/[&<>"']/g, (char) => ({
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#039;',
  }[char]));
}

async function buscarProductos(q) {
  const results = $('resultadosBusqueda');
  if (q.trim().length < 2) {
    results.classList.add('d-none');
    results.innerHTML = '';
    return;
  }

  const response = await fetch(`${baseUrl}/actions/buscar_producto.php?q=${encodeURIComponent(q)}`);
  const products = await response.json();
  results.innerHTML = '';

  for (const product of products) {
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'search-result';
    button.innerHTML = `<span>${escapeHtml(product.codigo)}</span><strong>${escapeHtml(product.descripcion)}</strong>`;
    button.addEventListener('click', () => addProductLine(product));
    results.appendChild(button);
  }

  results.classList.toggle('d-none', products.length === 0);
}

function focusQuantity(productId) {
  requestAnimationFrame(() => {
    const input = document.querySelector(`[data-edit="${productId}"]`);
    if (!input) return;
    input.focus();
    input.select();
    input.scrollIntoView({ behavior: 'smooth', block: 'center' });
  });
}

function addProductLine(product) {
  const id = String(product.id);
  if (!state.items.has(id)) {
    state.items.set(id, {
      producto_id: Number(product.id),
      codigo: product.codigo,
      descripcion: product.descripcion,
      cantidad: '',
    });
    renderList();
  } else {
    showMessage('Producto ya estaba agregado. Actualice la cantidad.', 'warning');
  }

  $('resultadosBusqueda').classList.add('d-none');
  $('buscarProducto').value = '';
  focusQuantity(product.id);
}

async function guardarBorrador(auto = false) {
  if (state.saving || state.items.size === 0) return null;
  state.saving = true;
  try {
    const response = await fetch(`${baseUrl}/actions/guardar_borrador.php`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(buildPayload()),
    });
    const data = await response.json();
    if (!response.ok || !data.ok) throw new Error(data.message || 'Error');
    $('conteoId').value = data.conteo_id;
    showMessage(auto ? 'Borrador guardado automaticamente' : data.message);
    return data;
  } catch (error) {
    showMessage('No se pudo guardar el borrador', 'danger');
    return null;
  } finally {
    state.saving = false;
  }
}

async function finalizarConteo() {
  if (state.items.size === 0) {
    showMessage('Agregue productos antes de finalizar', 'warning');
    return;
  }
  if (!confirm('Finalizar conteo? No podra editarlo despues.')) return;

  try {
    const response = await fetch(`${baseUrl}/actions/finalizar_conteo.php`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(buildPayload()),
    });
    const data = await response.json();
    if (!response.ok || !data.ok) throw new Error(data.message || 'Error');
    showMessage('Conteo finalizado correctamente');
    setTimeout(() => {
      window.location.href = `${baseUrl}/reportes.php?estado=finalizado`;
    }, 900);
  } catch (error) {
    showMessage('No se pudo finalizar el conteo', 'danger');
  }
}

async function crearConteo() {
  const agencia = $('agenciaConteo').value.trim().toUpperCase();
  const fechaHoraHabilitacion = $('fechaHoraHabilitacion').value;
  const fechaHoraCierre = $('fechaHoraCierre').value;
  const [fechaHabilitacion, horaInicio] = fechaHoraHabilitacion.split('T');
  const [fechaCierre, horaFin] = fechaHoraCierre.split('T');
  const nombre = buildNombreConteo();
  const usuarios = getUsuariosSeleccionados();
  if (!fechaHoraHabilitacion || !fechaHoraCierre || !fechaHabilitacion || !fechaCierre || !horaInicio || !horaFin) {
    showMessage('Complete fechas y horas de la toma', 'warning');
    return;
  }
  if (usuarios.length === 0) {
    showMessage('Seleccione al menos un usuario participante', 'warning');
    return;
  }

  try {
    const response = await fetch(`${baseUrl}/actions/crear_conteo.php`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        csrf_token: $('csrfToken').value,
        agencia,
        fecha_habilitacion: fechaHabilitacion,
        fecha_cierre: fechaCierre,
        hora_inicio: horaInicio,
        hora_fin: horaFin,
        usuarios,
      }),
    });
    const data = await response.json();
    if (!response.ok || !data.ok) throw new Error(data.message || 'Error');

    $('conteoId').value = data.conteo_id;
    if ($('numeroToma') && data.numero_toma) $('numeroToma').value = data.numero_toma;
    $('conteoCreado').value = '1';
    state.created = true;
    if (userRole === 'admin') {
      showMessage(`Toma creada para ${data.usuarios_asignados || usuarios.length} usuario(s).`);
      setTimeout(() => {
        window.location.href = `${baseUrl}/conteo.php`;
      }, 900);
      return;
    }
    $('crearConteoPanel')?.classList.add('d-none');
    $('conteoWorkspace')?.classList.remove('d-none');
    $('accionesConteo')?.classList.remove('d-none');
    $('operacionActiva')?.classList.remove('d-none');
    if ($('operacionNombre')) $('operacionNombre').textContent = data.nombre_conteo || nombre;
    if ($('nombreConteo')) $('nombreConteo').value = data.nombre_conteo || nombre;
    showMessage('Conteo creado. Ya puede agregar productos.');
    $('buscarProducto')?.focus();
  } catch (error) {
    showMessage('No se pudo crear el conteo', 'danger');
  }
}

function getUsuariosSeleccionados() {
  return Array.from(document.querySelectorAll('.participant-check:checked'))
    .map((input) => Number(input.value))
    .filter((id) => id > 0);
}

function formatDateLabel(value) {
  if (!value) return '';
  const date = new Date(`${value}T00:00:00`);
  if (Number.isNaN(date.getTime())) return '';
  const days = ['DOMINGO', 'LUNES', 'MARTES', 'MIERCOLES', 'JUEVES', 'VIERNES', 'SABADO'];
  const dd = String(date.getDate()).padStart(2, '0');
  const mm = String(date.getMonth() + 1).padStart(2, '0');
  const yyyy = date.getFullYear();
  return `${days[date.getDay()]} ${dd}/${mm}/${yyyy}`;
}

function buildNombreConteo() {
  const numeroToma = $('numeroToma')?.value.trim().toUpperCase() || '';
  const agencia = $('agenciaConteo')?.value.trim().toUpperCase() || '';
  const [fechaHabilitacionRaw = '', horaInicio = ''] = ($('fechaHoraHabilitacion')?.value || '').split('T');
  const [fechaCierreRaw = '', horaFin = ''] = ($('fechaHoraCierre')?.value || '').split('T');
  const fechaHabilitacion = formatDateLabel(fechaHabilitacionRaw);
  const fechaCierre = formatDateLabel(fechaCierreRaw);
  const nombre = `TOMA FISICA # ${numeroToma}\nAGENCIA: ${agencia}\nHABILITACION: ${fechaHabilitacion} ${horaInicio}\nFINALIZACION: ${fechaCierre} ${horaFin}`;
  if ($('nombreConteo')) $('nombreConteo').value = nombre;
  return nombre;
}

function renderNombrePreview() {
  const preview = $('vistaNombreConteo');
  if (!preview) return;
  preview.textContent = buildNombreConteo();
}

function buildPayload() {
  return {
    csrf_token: $('csrfToken').value,
    conteo_id: Number($('conteoId').value || 0),
    nombre_conteo: $('nombreConteo')?.value.trim() || '',
    items: Array.from(state.items.values()),
  };
}

let searchTimer = null;
$('buscarProducto')?.addEventListener('input', (event) => {
  clearTimeout(searchTimer);
  searchTimer = setTimeout(() => buscarProductos(event.target.value), 220);
});

$('crearConteo')?.addEventListener('click', crearConteo);
$('seleccionarUsuarios')?.addEventListener('click', () => {
  document.querySelectorAll('.participant-check').forEach((input) => {
    input.checked = true;
  });
});
$('limpiarUsuarios')?.addEventListener('click', () => {
  document.querySelectorAll('.participant-check').forEach((input) => {
    input.checked = false;
  });
});
$('numeroToma')?.addEventListener('keydown', (event) => {
  if (event.key === 'Enter' && !state.created) crearConteo();
});
$('agenciaConteo')?.addEventListener('keydown', (event) => {
  if (event.key === 'Enter' && !state.created) crearConteo();
});
for (const id of ['numeroToma', 'agenciaConteo', 'fechaHoraHabilitacion', 'fechaHoraCierre']) {
  $(id)?.addEventListener('input', renderNombrePreview);
}
$('guardarBorrador')?.addEventListener('click', () => guardarBorrador(false));
$('finalizarConteo')?.addEventListener('click', finalizarConteo);
$('listaProductos')?.addEventListener('input', (event) => {
  const id = event.target.dataset.edit;
  if (!id || !state.items.has(id)) return;
  state.items.get(id).cantidad = event.target.value;
});
$('listaProductos')?.addEventListener('keydown', (event) => {
  if (event.key !== 'Enter' || !event.target.dataset.edit) return;
  event.preventDefault();
  $('buscarProducto')?.focus();
});
$('listaProductos')?.addEventListener('click', (event) => {
  const button = event.target.closest('[data-delete]');
  if (!button) return;
  state.items.delete(button.dataset.delete);
  renderList();
});

setInterval(() => guardarBorrador(true), 30000);
renderNombrePreview();
renderList();
