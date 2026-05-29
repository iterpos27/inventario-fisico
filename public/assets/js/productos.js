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

const importJobPanel = document.getElementById('importJobPanel');
const importJobProgress = document.getElementById('importJobProgress');
const importJobStatus = document.getElementById('importJobStatus');
const importJobText = document.getElementById('importJobText');

function renderImportJob(job) {
  if (!job || !importJobProgress || !importJobStatus || !importJobText) return;
  const total = Number(job.total_rows || 0);
  const current = Math.min(Number(job.current_row || 2), total || 2);
  const percent = total > 1 ? Math.min(100, Math.round(((current - 1) / (total - 1)) * 100)) : 0;
  importJobProgress.style.width = `${percent}%`;
  importJobProgress.textContent = `${percent}%`;
  importJobStatus.textContent = job.estado || 'pendiente';
  importJobText.textContent = `Procesados: ${job.procesados || 0}. Insertados: ${job.insertados || 0}. Actualizados: ${job.actualizados || 0}. Omitidos: ${job.omitidos || 0}.`;
}

async function processImportJob() {
  if (!importJobPanel) return;
  const jobId = importJobPanel.dataset.jobId;
  const csrfToken = importJobPanel.dataset.csrf || '';
  if (!jobId) return;
  try {
    const response = await fetch(`${window.BASE_URL || ''}/actions/import_job_process?job_id=${encodeURIComponent(jobId)}`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({ csrf_token: csrfToken }),
    });
    const data = await response.json();
    if (!response.ok || !data.ok) throw new Error(data.message || 'Error');
    renderImportJob(data.job);
    if (['finalizado', 'fallido'].includes(data.job.estado)) {
      if (data.job.estado === 'finalizado') {
        setTimeout(() => window.location.href = `${window.BASE_URL || ''}/productos?msg=Importacion completada: ${data.job.procesados} productos procesados`, 900);
      }
      return;
    }
    setTimeout(processImportJob, 450);
  } catch (error) {
    if (importJobStatus) importJobStatus.textContent = 'fallido';
    if (importJobText) importJobText.textContent = 'No se pudo procesar la importacion. Revise logs.';
  }
}

if (importJobPanel) {
  processImportJob();
}
