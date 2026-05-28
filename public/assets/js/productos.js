const modalEditarProducto = document.getElementById('modalEditarProducto');
const formBuscarProductoAdmin = document.getElementById('formBuscarProductoAdmin');
const buscarProductoAdmin = document.getElementById('buscarProductoAdmin');

formBuscarProductoAdmin?.addEventListener('submit', (event) => {
  const value = buscarProductoAdmin?.value.trim() || '';
  if (value !== '' && value.length < 3) {
    event.preventDefault();
    buscarProductoAdmin?.focus();
  }
});

modalEditarProducto?.addEventListener('show.bs.modal', (event) => {
  const button = event.relatedTarget;
  if (!button) return;

  document.getElementById('editarProductoId').value = button.dataset.productId || '';
  document.getElementById('editarProductoCodigo').value = button.dataset.productCode || '';
  document.getElementById('editarProductoDescripcion').value = button.dataset.productDescription || '';
});

document.querySelectorAll('[data-delete-product]').forEach((form) => {
  form.addEventListener('submit', (event) => {
    if (!confirm('Eliminar este producto del inventario activo?')) {
      event.preventDefault();
    }
  });
});
