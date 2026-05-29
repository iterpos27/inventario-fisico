const $ = (id) => document.getElementById(id);
const baseUrl = window.BASE_URL || '';
const userRole = window.USER_ROLE || 'usuario';
const state = {
  items: new Map(),
  saving: false,
  created: $('conteoCreado')?.value === '1',
  pendingDeleteId: null,
  highlightedId: null,
  highlightTimer: null,
  saveModalTimer: null,
  scannerStream: null,
  scannerTimer: null,
  scannerActive: false,
};

for (const item of window.CONTEO_INICIAL || []) {
  state.items.set(String(item.producto_id), {
    producto_id: Number(item.producto_id),
    codigo: item.codigo,
    descripcion: item.descripcion,
    cantidad: Number(item.cantidad),
  });
}

function moveItemFirst(id) {
  const key = String(id);
  if (!state.items.has(key)) return;
  const item = state.items.get(key);
  state.items.delete(key);
  state.items = new Map([[key, item], ...state.items]);
}

function highlightItem(id) {
  state.highlightedId = String(id);
  clearTimeout(state.highlightTimer);
  state.highlightTimer = setTimeout(() => {
    state.highlightedId = null;
    renderList();
  }, 1300);
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
    const isHighlighted = String(item.producto_id) === state.highlightedId;
    const quantity = Number.parseFloat(item.cantidad || 0);
    const isEmptyQuantity = !item.cantidad || Number.isNaN(quantity) || quantity <= 0;
    const row = document.createElement('div');
    row.className = `count-item${isHighlighted ? ' is-flashing' : ''}${isEmptyQuantity ? ' is-empty-quantity' : ' has-quantity'}`;
    row.innerHTML = `
      <button class="btn btn-outline-danger count-item-delete" type="button" data-delete="${item.producto_id}" aria-label="Eliminar"><i class="bi bi-trash"></i></button>
      <div class="count-item-main">
        <span class="product-code">${escapeHtml(item.codigo)}</span>
        <strong>${escapeHtml(item.descripcion)}</strong>
      </div>
      <div class="count-item-actions">
        <input class="form-control" type="number" step="0.01" min="0" inputmode="decimal" value="${item.cantidad || ''}" placeholder="Cantidad" data-edit="${item.producto_id}">
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

let searchAbortController = null;
let searchRequestId = 0;

async function buscarProductos(q) {
  const results = $('resultadosBusqueda');
  const term = q.trim();
  const requestId = searchRequestId + 1;
  searchRequestId = requestId;

  if (searchAbortController) {
    searchAbortController.abort();
  }

  if (term.length < 3) {
    results.classList.add('d-none');
    results.innerHTML = '';
    return;
  }

  searchAbortController = new AbortController();
  let products = [];
  try {
    const response = await fetch(`${baseUrl}/actions/buscar_producto?q=${encodeURIComponent(term)}`, {
      signal: searchAbortController.signal,
    });
    products = await response.json();
  } catch (error) {
    if (error.name === 'AbortError') return;
    results.innerHTML = '<div class="search-result text-danger">No se pudo buscar. Intente de nuevo.</div>';
    results.classList.remove('d-none');
    return;
  }

  if (requestId !== searchRequestId) return;
  results.innerHTML = '';

  for (const product of products) {
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'search-result';
    button.innerHTML = `<span>${escapeHtml(product.codigo)}</span><strong>${escapeHtml(product.descripcion)}</strong>`;
    button.addEventListener('click', (event) => {
      event.preventDefault();
      addProductLine(product);
    });
    results.appendChild(button);
  }

  results.classList.toggle('d-none', products.length === 0);
}

function focusQuantity(productId) {
  const focusInput = () => {
    const input = document.querySelector(`[data-edit="${productId}"]`);
    if (!(input instanceof HTMLInputElement)) return;
    input.scrollIntoView({ behavior: 'smooth', block: 'center' });
    input.focus({ preventScroll: true });
    try {
      input.select();
    } catch (error) {
      input.setSelectionRange?.(0, input.value.length);
    }
  };

  requestAnimationFrame(focusInput);
  setTimeout(focusInput, 40);
  setTimeout(focusInput, 140);
  setTimeout(focusInput, 280);
}

function addProductLine(product) {
  const id = String(product.id);
  if (!state.items.has(id)) {
    state.items = new Map([[id, {
      producto_id: Number(product.id),
      codigo: product.codigo,
      descripcion: product.descripcion,
      cantidad: '',
    }], ...state.items]);
  } else {
    moveItemFirst(id);
    showMessage('Producto ya estaba agregado. Actualice la cantidad.', 'warning');
  }

  highlightItem(id);
  renderList();
  setSaveStatus('Cambios pendientes por guardar.');
  $('resultadosBusqueda').classList.add('d-none');
  $('buscarProducto').value = '';
  toggleSearchClear();
  focusQuantity(product.id);
}

async function guardarBorrador(auto = false) {
  if (state.saving || state.items.size === 0) return null;
  state.saving = true;
  setSaveStatus(auto ? 'Autoguardando...' : 'Guardando borrador...');
  try {
    const response = await fetch(`${baseUrl}/actions/guardar_borrador`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(buildPayload()),
    });
    const data = await response.json();
    if (!response.ok || !data.ok) throw new Error(data.message || 'Error');
    $('conteoId').value = data.conteo_id;
    if ($('conteoVersion') && data.conteo_version !== undefined) {
      $('conteoVersion').value = data.conteo_version;
    }
    setSaveStatus(`Guardado ${formatTime(new Date())}`);
    if (!auto) showManualSaveModal();
    return data;
  } catch (error) {
    setSaveStatus('No se pudo guardar. Revise la conexion.');
    if (!auto) showMessage('No se pudo guardar el borrador', 'danger');
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
    const response = await fetch(`${baseUrl}/actions/finalizar_conteo`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(buildPayload()),
    });
    const data = await response.json();
    if (!response.ok || !data.ok) throw new Error(data.message || 'Error');
    if ($('conteoVersion') && data.conteo_version !== undefined) {
      $('conteoVersion').value = data.conteo_version;
    }
    showMessage('Conteo finalizado correctamente');
    setTimeout(() => {
      window.location.href = `${baseUrl}/reportes?estado=finalizado`;
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
    const response = await fetch(`${baseUrl}/actions/crear_conteo`, {
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
        window.location.href = `${baseUrl}/conteo`;
      }, 900);
      return;
    }
    $('crearConteoPanel')?.classList.add('d-none');
    $('conteoWorkspace')?.classList.remove('d-none');
    $('accionesConteo')?.classList.remove('d-none');
    $('operacionActiva')?.classList.remove('d-none');
    updateActiveOperationHeader(data.nombre_conteo || nombre);
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
    conteo_version: Number($('conteoVersion')?.value || 0),
    nombre_conteo: $('nombreConteo')?.value.trim() || '',
    items: Array.from(state.items.values()),
  };
}

let searchTimer = null;
$('buscarProducto')?.addEventListener('input', (event) => {
  clearTimeout(searchTimer);
  toggleSearchClear();
  searchTimer = setTimeout(() => buscarProductos(event.target.value), 420);
});

$('limpiarBusqueda')?.addEventListener('click', () => {
  clearSearch();
  $('buscarProducto')?.focus();
});
$('abrirEscaner')?.addEventListener('click', () => {
  startScanner();
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
  setSaveStatus('Cambios pendientes por guardar.');
});
$('listaProductos')?.addEventListener('keydown', (event) => {
  if (event.key !== 'Enter' || !event.target.dataset.edit) return;
  event.preventDefault();
  $('buscarProducto')?.focus();
});

$('listaProductos')?.addEventListener('click', (event) => {
  const button = event.target.closest('[data-delete]');
  if (!button) return;
  state.pendingDeleteId = button.dataset.delete;
  const modalElement = $('modalEliminarProductoConteo');
  if (!modalElement || !window.bootstrap) {
    deletePendingProduct();
    return;
  }
  window.bootstrap.Modal.getOrCreateInstance(modalElement).show();
});

$('confirmarEliminarProductoConteo')?.addEventListener('click', () => {
  deletePendingProduct();
  const modalElement = $('modalEliminarProductoConteo');
  if (modalElement && window.bootstrap) {
    window.bootstrap.Modal.getOrCreateInstance(modalElement).hide();
  }
});

function deletePendingProduct() {
  if (!state.pendingDeleteId) return;
  state.items.delete(state.pendingDeleteId);
  state.pendingDeleteId = null;
  renderList();
  setSaveStatus('Cambios pendientes por guardar.');
}

function updateActiveOperationHeader(text) {
  if (!text) return;
  const lines = text.split('\n');
  const toma = (lines[0] || '').trim();
  const agencia = (lines[1] || '').replace('AGENCIA:', '').trim();
  const hab = (lines[2] || '').replace('HABILITACION:', '').trim();
  const fin = (lines[3] || '').replace('FINALIZACION:', '').trim();

  if ($('operacionToma')) $('operacionToma').textContent = toma;
  if ($('operacionAgencia')) $('operacionAgencia').textContent = agencia || 'Sin agencia';
  if ($('operacionHabilitacion')) $('operacionHabilitacion').textContent = hab;
  if ($('operacionFinalizacion')) $('operacionFinalizacion').textContent = fin;
}

function setSaveStatus(text) {
  const status = $('estadoGuardado');
  if (status) status.textContent = text;
}

function showManualSaveModal() {
  const modalElement = $('modalBorradorGuardado');
  if (!modalElement || !window.bootstrap) {
    showMessage('Proceso guardado');
    return;
  }

  clearTimeout(state.saveModalTimer);
  const modal = window.bootstrap.Modal.getOrCreateInstance(modalElement);
  modal.show();
  state.saveModalTimer = setTimeout(() => modal.hide(), 5000);
}

async function startScanner() {
  if (!('mediaDevices' in navigator) || !navigator.mediaDevices.getUserMedia) {
    showMessage('Este navegador no permite usar la camara.', 'warning');
    return;
  }

  if (!('BarcodeDetector' in window)) {
    showMessage('Este navegador no soporta lectura QR/codigo. Use Chrome o Edge actualizado.', 'warning');
    return;
  }

  const modalElement = $('modalEscanerProducto');
  const video = $('videoEscanerProducto');
  const status = $('estadoEscanerProducto');
  if (!modalElement || !video || !status || !window.bootstrap) {
    showMessage('No se pudo abrir el escaner.', 'danger');
    return;
  }

  try {
    status.textContent = 'Solicitando camara...';
    const stream = await navigator.mediaDevices.getUserMedia({
      video: {
        facingMode: { ideal: 'environment' },
        width: { ideal: 1280 },
        height: { ideal: 720 },
      },
      audio: false,
    });
    state.scannerStream = stream;
    video.srcObject = stream;
    await video.play();

    const modal = window.bootstrap.Modal.getOrCreateInstance(modalElement);
    modal.show();
    status.textContent = 'Apunte la camara al codigo.';
    scanLoop(new window.BarcodeDetector({
      formats: ['qr_code', 'code_128', 'code_39', 'code_93', 'ean_13', 'ean_8', 'upc_a', 'upc_e', 'itf'],
    }));
  } catch (error) {
    stopScanner();
    status.textContent = 'No se pudo abrir la camara.';
    showMessage('Permita el acceso a la camara para escanear.', 'danger');
  }
}

function scanLoop(detector) {
  const video = $('videoEscanerProducto');
  const status = $('estadoEscanerProducto');
  state.scannerActive = true;

  const scan = async () => {
    if (!state.scannerActive || !video) return;
    try {
      const codes = await detector.detect(video);
      if (codes.length > 0) {
        const value = (codes[0].rawValue || '').trim();
        if (value !== '') {
          if (status) status.textContent = `Codigo detectado: ${value}`;
          applyScannedCode(value);
          return;
        }
      }
    } catch (error) {
      if (status) status.textContent = 'No se pudo leer el codigo. Ajuste la distancia.';
    }

    state.scannerTimer = setTimeout(scan, 250);
  };

  scan();
}

function applyScannedCode(value) {
  const input = $('buscarProducto');
  if (input) {
    input.value = value;
    toggleSearchClear();
    buscarProductos(value);
  }
  const modalElement = $('modalEscanerProducto');
  if (modalElement && window.bootstrap) {
    window.bootstrap.Modal.getOrCreateInstance(modalElement).hide();
  }
  stopScanner();
}

function stopScanner() {
  state.scannerActive = false;
  clearTimeout(state.scannerTimer);
  state.scannerTimer = null;
  if (state.scannerStream) {
    state.scannerStream.getTracks().forEach((track) => track.stop());
    state.scannerStream = null;
  }
  const video = $('videoEscanerProducto');
  if (video) {
    video.pause();
    video.srcObject = null;
  }
}

function formatTime(date) {
  return date.toLocaleTimeString('es-DO', { hour: '2-digit', minute: '2-digit' });
}

function toggleSearchClear() {
  const input = $('buscarProducto');
  $('limpiarBusqueda')?.classList.toggle('d-none', !input || input.value.trim() === '');
}

function clearSearch() {
  const input = $('buscarProducto');
  if (searchAbortController) {
    searchAbortController.abort();
  }
  searchRequestId += 1;
  if (input) input.value = '';
  $('resultadosBusqueda')?.classList.add('d-none');
  if ($('resultadosBusqueda')) $('resultadosBusqueda').innerHTML = '';
  toggleSearchClear();
}

setInterval(() => guardarBorrador(true), 30000);
$('modalEscanerProducto')?.addEventListener('hidden.bs.modal', stopScanner);
renderNombrePreview();
renderList();
toggleSearchClear();
