const productosBaseUrl = window.BASE_URL || '';
const productosInput = document.getElementById('buscarProductoAdmin');
const productosBody = document.getElementById('productosResultados');
let productosTimer = null;

function escapeProductHtml(value) {
  return String(value).replace(/[&<>"']/g, (char) => ({
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#039;',
  }[char]));
}

function renderProductos(products, message = '') {
  if (!productosBody) return;
  if (message) {
    productosBody.innerHTML = `<tr><td colspan="2" class="text-center text-secondary py-4">${escapeProductHtml(message)}</td></tr>`;
    return;
  }
  if (!products.length) {
    productosBody.innerHTML = '<tr><td colspan="2" class="text-center text-secondary py-4">No se encontraron productos.</td></tr>';
    return;
  }
  productosBody.innerHTML = products.map((product) => `
    <tr>
      <td class="fw-semibold">${escapeProductHtml(product.codigo)}</td>
      <td>${escapeProductHtml(product.descripcion)}</td>
    </tr>
  `).join('');
}

async function buscarProductosAdmin(query) {
  const q = query.trim();
  if (q.length < 2) {
    renderProductos([], 'Busque por codigo o descripcion.');
    return;
  }

  try {
    const response = await fetch(`${productosBaseUrl}/actions/buscar_producto.php?q=${encodeURIComponent(q)}`);
    const products = await response.json();
    renderProductos(products);
  } catch (error) {
    renderProductos([], 'No se pudo buscar productos.');
  }
}

productosInput?.addEventListener('input', (event) => {
  clearTimeout(productosTimer);
  productosTimer = setTimeout(() => buscarProductosAdmin(event.target.value), 220);
});
