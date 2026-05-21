const state = {
  selected: null,
  items: new Map(),
  saving: false,
};

const $ = (id) => document.getElementById(id);
const baseUrl = window.BASE_URL || '';

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
        <input class="form-control" type="number" step="0.01" min="0" inputmode="decimal" value="${item.cantidad}" data-edit="${item.producto_id}">
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
    button.addEventListener('click', () => selectProduct(product));
    results.appendChild(button);
  }

  results.classList.toggle('d-none', products.length === 0);
}

function selectProduct(product) {
  state.selected = {
    producto_id: Number(product.id),
    codigo: product.codigo,
    descripcion: product.descripcion,
  };
  $('selCodigo').textContent = product.codigo;
  $('selDescripcion').textContent = product.descripcion;
  $('productoSeleccionado').classList.remove('d-none');
  $('resultadosBusqueda').classList.add('d-none');
  $('buscarProducto').value = '';
  $('cantidadProducto').value = state.items.get(String(product.id))?.cantidad || '';
  $('cantidadProducto').focus();
}

function addSelected() {
  if (!state.selected) {
    showMessage('Seleccione un producto', 'warning');
    return;
  }
  const cantidad = Number($('cantidadProducto').value);
  if (!Number.isFinite(cantidad) || cantidad < 0) {
    showMessage('Ingrese una cantidad valida', 'warning');
    return;
  }
  state.items.set(String(state.selected.producto_id), {
    ...state.selected,
    cantidad,
  });
  state.selected = null;
  $('productoSeleccionado').classList.add('d-none');
  $('cantidadProducto').value = '';
  $('buscarProducto').focus();
  renderList();
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

function buildPayload() {
  return {
    csrf_token: $('csrfToken').value,
    conteo_id: Number($('conteoId').value || 0),
    nombre_conteo: $('nombreConteo').value.trim(),
    items: Array.from(state.items.values()),
  };
}

let searchTimer = null;
$('buscarProducto').addEventListener('input', (event) => {
  clearTimeout(searchTimer);
  searchTimer = setTimeout(() => buscarProductos(event.target.value), 220);
});

$('agregarProducto').addEventListener('click', addSelected);
$('cantidadProducto').addEventListener('keydown', (event) => {
  if (event.key === 'Enter') addSelected();
});
$('guardarBorrador').addEventListener('click', () => guardarBorrador(false));
$('finalizarConteo').addEventListener('click', finalizarConteo);
$('listaProductos').addEventListener('input', (event) => {
  const id = event.target.dataset.edit;
  if (!id || !state.items.has(id)) return;
  state.items.get(id).cantidad = Number(event.target.value || 0);
});
$('listaProductos').addEventListener('click', (event) => {
  const button = event.target.closest('[data-delete]');
  if (!button) return;
  state.items.delete(button.dataset.delete);
  renderList();
});

setInterval(() => guardarBorrador(true), 30000);
renderList();
