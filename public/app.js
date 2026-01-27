const API_BASE = '/api';

// Data cache
let collectors = [];
let sommeliers = [];
let bottles = [];
let agingRecords = [];
let tastings = [];
let editingId = null;
let editingType = null;

// Initialize
document.addEventListener('DOMContentLoaded', () => {
  loadAllData();
  setupNavigation();
});

// Navigation
function setupNavigation() {
  document.querySelectorAll('.nav-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('.nav-btn').forEach(b => b.classList.remove('active'));
      document.querySelectorAll('.section').forEach(s => s.classList.remove('active'));
      btn.classList.add('active');
      document.getElementById(btn.dataset.section).classList.add('active');
    });
  });
}

// Load all data
async function loadAllData() {
  await Promise.all([
    loadCollectors(),
    loadSommeliers(),
    loadBottles(),
    loadAgingRecords(),
    loadTastings()
  ]);
}

// API calls
async function loadCollectors() {
  const response = await fetch(`${API_BASE}/collectors`);
  collectors = await response.json();
  renderCollectors();
}

async function loadSommeliers() {
  const response = await fetch(`${API_BASE}/sommeliers`);
  sommeliers = await response.json();
  renderSommeliers();
}

async function loadBottles() {
  const denomination = document.getElementById('denomination-filter')?.value || '';
  const url = denomination ? `${API_BASE}/bottles?denomination=${denomination}` : `${API_BASE}/bottles`;
  const response = await fetch(url);
  bottles = await response.json();
  renderBottles();
}

async function loadAgingRecords() {
  const response = await fetch(`${API_BASE}/aging`);
  agingRecords = await response.json();
  renderAgingRecords();
}

async function loadTastings() {
  const response = await fetch(`${API_BASE}/tastings`);
  tastings = await response.json();
  renderTastings();
}

function filterBottles() {
  loadBottles();
}

// Render functions
function renderCollectors() {
  const container = document.getElementById('collectors-list');
  if (collectors.length === 0) {
    container.innerHTML = '<p class="empty-message">No hay coleccionistas registrados</p>';
    return;
  }
  container.innerHTML = collectors.map(c => `
    <div class="card">
      <h3>${escapeHtml(c.name)}</h3>
      <p><strong>Email:</strong> ${escapeHtml(c.email)}</p>
      ${c.phone ? `<p><strong>Teléfono:</strong> ${escapeHtml(c.phone)}</p>` : ''}
      ${c.address ? `<p><strong>Dirección:</strong> ${escapeHtml(c.address)}</p>` : ''}
      <div class="card-actions">
        <button class="btn-edit" onclick="editItem('collector', ${c.id})">Editar</button>
        <button class="btn-delete" onclick="deleteItem('collector', ${c.id})">Eliminar</button>
      </div>
    </div>
  `).join('');
}

function renderSommeliers() {
  const container = document.getElementById('sommeliers-list');
  if (sommeliers.length === 0) {
    container.innerHTML = '<p class="empty-message">No hay sommeliers registrados</p>';
    return;
  }
  container.innerHTML = sommeliers.map(s => `
    <div class="card">
      <h3>${escapeHtml(s.name)}</h3>
      <p><strong>Email:</strong> ${escapeHtml(s.email)}</p>
      <p><strong>Nivel de Certificación:</strong> ${escapeHtml(s.certification_level)}</p>
      ${s.certification_date ? `<p><strong>Fecha de Certificación:</strong> ${escapeHtml(s.certification_date)}</p>` : ''}
      ${s.specialization ? `<p><strong>Especialización:</strong> ${escapeHtml(s.specialization)}</p>` : ''}
      <div class="card-actions">
        <button class="btn-edit" onclick="editItem('sommelier', ${s.id})">Editar</button>
        <button class="btn-delete" onclick="deleteItem('sommelier', ${s.id})">Eliminar</button>
      </div>
    </div>
  `).join('');
}

function renderBottles() {
  const container = document.getElementById('bottles-list');
  if (bottles.length === 0) {
    container.innerHTML = '<p class="empty-message">No hay botellas registradas</p>';
    return;
  }
  container.innerHTML = bottles.map(b => `
    <div class="card">
      <h3>${escapeHtml(b.name)}</h3>
      <span class="denomination-badge denomination-${b.denomination}">${escapeHtml(b.denomination)}</span>
      <p><strong>Productor:</strong> ${escapeHtml(b.producer)}</p>
      ${b.vintage ? `<p><strong>Añada:</strong> ${b.vintage}</p>` : ''}
      ${b.region ? `<p><strong>Región:</strong> ${escapeHtml(b.region)}</p>` : ''}
      ${b.grape_variety ? `<p><strong>Variedad:</strong> ${escapeHtml(b.grape_variety)}</p>` : ''}
      ${b.alcohol_content ? `<p><strong>Alcohol:</strong> ${b.alcohol_content}%</p>` : ''}
      ${b.quantity ? `<p><strong>Cantidad:</strong> ${b.quantity}</p>` : ''}
      <div class="card-actions">
        <button class="btn-edit" onclick="editItem('bottle', ${b.id})">Editar</button>
        <button class="btn-delete" onclick="deleteItem('bottle', ${b.id})">Eliminar</button>
      </div>
    </div>
  `).join('');
}

function renderAgingRecords() {
  const container = document.getElementById('aging-list');
  if (agingRecords.length === 0) {
    container.innerHTML = '<p class="empty-message">No hay registros de envejecimiento</p>';
    return;
  }
  container.innerHTML = agingRecords.map(a => `
    <div class="card">
      <h3>${escapeHtml(a.bottle_name || 'Botella #' + a.bottle_id)}</h3>
      <p><strong>Fecha de inicio:</strong> ${escapeHtml(a.start_date)}</p>
      ${a.end_date ? `<p><strong>Fecha de fin:</strong> ${escapeHtml(a.end_date)}</p>` : ''}
      ${a.storage_location ? `<p><strong>Ubicación:</strong> ${escapeHtml(a.storage_location)}</p>` : ''}
      ${a.temperature ? `<p><strong>Temperatura:</strong> ${a.temperature}°C</p>` : ''}
      ${a.humidity ? `<p><strong>Humedad:</strong> ${a.humidity}%</p>` : ''}
      ${a.notes ? `<p><strong>Notas:</strong> ${escapeHtml(a.notes)}</p>` : ''}
      <div class="card-actions">
        <button class="btn-edit" onclick="editItem('aging', ${a.id})">Editar</button>
        <button class="btn-delete" onclick="deleteItem('aging', ${a.id})">Eliminar</button>
      </div>
    </div>
  `).join('');
}

function renderTastings() {
  const container = document.getElementById('tastings-list');
  if (tastings.length === 0) {
    container.innerHTML = '<p class="empty-message">No hay catas registradas</p>';
    return;
  }
  container.innerHTML = tastings.map(t => `
    <div class="card">
      <h3>${escapeHtml(t.bottle_name || 'Botella #' + t.bottle_id)}</h3>
      ${t.overall_rating ? `<p class="rating">★ ${t.overall_rating}/100</p>` : ''}
      <p><strong>Fecha:</strong> ${escapeHtml(t.tasting_date)}</p>
      ${t.sommelier_name ? `<p><strong>Sommelier:</strong> ${escapeHtml(t.sommelier_name)}</p>` : ''}
      ${t.appearance ? `<p><strong>Apariencia:</strong> ${escapeHtml(t.appearance)}</p>` : ''}
      ${t.aroma ? `<p><strong>Aroma:</strong> ${escapeHtml(t.aroma)}</p>` : ''}
      ${t.taste ? `<p><strong>Sabor:</strong> ${escapeHtml(t.taste)}</p>` : ''}
      ${t.finish ? `<p><strong>Final:</strong> ${escapeHtml(t.finish)}</p>` : ''}
      ${t.notes ? `<p><strong>Notas:</strong> ${escapeHtml(t.notes)}</p>` : ''}
      <div class="card-actions">
        <button class="btn-edit" onclick="editItem('tasting', ${t.id})">Editar</button>
        <button class="btn-delete" onclick="deleteItem('tasting', ${t.id})">Eliminar</button>
      </div>
    </div>
  `).join('');
}

// Modal functions
function showModal(type, data = null) {
  editingType = type;
  editingId = data?.id || null;
  
  const modal = document.getElementById('modal');
  const title = document.getElementById('modal-title');
  const form = document.getElementById('modal-form');
  
  let formContent = '';
  
  switch(type) {
    case 'collector':
      title.textContent = data ? 'Editar Coleccionista' : 'Nuevo Coleccionista';
      formContent = `
        <div class="form-group">
          <label for="name">Nombre *</label>
          <input type="text" id="name" required value="${escapeHtml(data?.name || '')}">
        </div>
        <div class="form-group">
          <label for="email">Email *</label>
          <input type="email" id="email" required value="${escapeHtml(data?.email || '')}">
        </div>
        <div class="form-group">
          <label for="phone">Teléfono</label>
          <input type="tel" id="phone" value="${escapeHtml(data?.phone || '')}">
        </div>
        <div class="form-group">
          <label for="address">Dirección</label>
          <input type="text" id="address" value="${escapeHtml(data?.address || '')}">
        </div>
      `;
      break;
      
    case 'sommelier':
      title.textContent = data ? 'Editar Sommelier' : 'Nuevo Sommelier';
      formContent = `
        <div class="form-group">
          <label for="name">Nombre *</label>
          <input type="text" id="name" required value="${escapeHtml(data?.name || '')}">
        </div>
        <div class="form-group">
          <label for="email">Email *</label>
          <input type="email" id="email" required value="${escapeHtml(data?.email || '')}">
        </div>
        <div class="form-group">
          <label for="certification_level">Nivel de Certificación *</label>
          <select id="certification_level" required>
            <option value="">Seleccionar...</option>
            <option value="Level 1" ${data?.certification_level === 'Level 1' ? 'selected' : ''}>Nivel 1 - Introductorio</option>
            <option value="Level 2" ${data?.certification_level === 'Level 2' ? 'selected' : ''}>Nivel 2 - Certificado</option>
            <option value="Level 3" ${data?.certification_level === 'Level 3' ? 'selected' : ''}>Nivel 3 - Avanzado</option>
            <option value="Level 4" ${data?.certification_level === 'Level 4' ? 'selected' : ''}>Nivel 4 - Master Sommelier</option>
          </select>
        </div>
        <div class="form-group">
          <label for="certification_date">Fecha de Certificación</label>
          <input type="date" id="certification_date" value="${data?.certification_date || ''}">
        </div>
        <div class="form-group">
          <label for="specialization">Especialización</label>
          <input type="text" id="specialization" value="${escapeHtml(data?.specialization || '')}">
        </div>
      `;
      break;
      
    case 'bottle':
      title.textContent = data ? 'Editar Botella' : 'Nueva Botella';
      formContent = `
        <div class="form-group">
          <label for="name">Nombre del Vino *</label>
          <input type="text" id="name" required value="${escapeHtml(data?.name || '')}">
        </div>
        <div class="form-group">
          <label for="producer">Productor *</label>
          <input type="text" id="producer" required value="${escapeHtml(data?.producer || '')}">
        </div>
        <div class="form-group">
          <label for="denomination">Denominación *</label>
          <select id="denomination" required>
            <option value="">Seleccionar...</option>
            <option value="DOCG" ${data?.denomination === 'DOCG' ? 'selected' : ''}>DOCG</option>
            <option value="DOC" ${data?.denomination === 'DOC' ? 'selected' : ''}>DOC</option>
            <option value="IGT" ${data?.denomination === 'IGT' ? 'selected' : ''}>IGT</option>
            <option value="organic" ${data?.denomination === 'organic' ? 'selected' : ''}>Orgánico</option>
          </select>
        </div>
        <div class="form-group">
          <label for="vintage">Añada</label>
          <input type="number" id="vintage" min="1900" max="2100" value="${data?.vintage || ''}">
        </div>
        <div class="form-group">
          <label for="region">Región</label>
          <input type="text" id="region" value="${escapeHtml(data?.region || '')}">
        </div>
        <div class="form-group">
          <label for="grape_variety">Variedad de Uva</label>
          <input type="text" id="grape_variety" value="${escapeHtml(data?.grape_variety || '')}">
        </div>
        <div class="form-group">
          <label for="alcohol_content">Contenido de Alcohol (%)</label>
          <input type="number" id="alcohol_content" step="0.1" min="0" max="100" value="${data?.alcohol_content || ''}">
        </div>
        <div class="form-group">
          <label for="quantity">Cantidad</label>
          <input type="number" id="quantity" min="1" value="${data?.quantity || 1}">
        </div>
        <div class="form-group">
          <label for="collector_id">Coleccionista</label>
          <select id="collector_id">
            <option value="">Sin asignar</option>
            ${collectors.map(c => `<option value="${c.id}" ${data?.collector_id === c.id ? 'selected' : ''}>${escapeHtml(c.name)}</option>`).join('')}
          </select>
        </div>
      `;
      break;
      
    case 'aging':
      title.textContent = data ? 'Editar Registro de Envejecimiento' : 'Nuevo Registro de Envejecimiento';
      formContent = `
        <div class="form-group">
          <label for="bottle_id">Botella *</label>
          <select id="bottle_id" required>
            <option value="">Seleccionar...</option>
            ${bottles.map(b => `<option value="${b.id}" ${data?.bottle_id === b.id ? 'selected' : ''}>${escapeHtml(b.name)} (${b.vintage || 'S/A'})</option>`).join('')}
          </select>
        </div>
        <div class="form-group">
          <label for="start_date">Fecha de Inicio *</label>
          <input type="date" id="start_date" required value="${data?.start_date || ''}">
        </div>
        <div class="form-group">
          <label for="end_date">Fecha de Fin</label>
          <input type="date" id="end_date" value="${data?.end_date || ''}">
        </div>
        <div class="form-group">
          <label for="storage_location">Ubicación de Almacenamiento</label>
          <input type="text" id="storage_location" value="${escapeHtml(data?.storage_location || '')}">
        </div>
        <div class="form-group">
          <label for="temperature">Temperatura (°C)</label>
          <input type="number" id="temperature" step="0.1" value="${data?.temperature || ''}">
        </div>
        <div class="form-group">
          <label for="humidity">Humedad (%)</label>
          <input type="number" id="humidity" step="0.1" min="0" max="100" value="${data?.humidity || ''}">
        </div>
        <div class="form-group">
          <label for="notes">Notas</label>
          <textarea id="notes">${escapeHtml(data?.notes || '')}</textarea>
        </div>
      `;
      break;
      
    case 'tasting':
      title.textContent = data ? 'Editar Cata' : 'Nueva Cata';
      formContent = `
        <div class="form-group">
          <label for="bottle_id">Botella *</label>
          <select id="bottle_id" required>
            <option value="">Seleccionar...</option>
            ${bottles.map(b => `<option value="${b.id}" ${data?.bottle_id === b.id ? 'selected' : ''}>${escapeHtml(b.name)} (${b.vintage || 'S/A'})</option>`).join('')}
          </select>
        </div>
        <div class="form-group">
          <label for="sommelier_id">Sommelier</label>
          <select id="sommelier_id">
            <option value="">Sin asignar</option>
            ${sommeliers.map(s => `<option value="${s.id}" ${data?.sommelier_id === s.id ? 'selected' : ''}>${escapeHtml(s.name)}</option>`).join('')}
          </select>
        </div>
        <div class="form-group">
          <label for="tasting_date">Fecha de Cata *</label>
          <input type="date" id="tasting_date" required value="${data?.tasting_date || ''}">
        </div>
        <div class="form-group">
          <label for="appearance">Apariencia</label>
          <textarea id="appearance">${escapeHtml(data?.appearance || '')}</textarea>
        </div>
        <div class="form-group">
          <label for="aroma">Aroma</label>
          <textarea id="aroma">${escapeHtml(data?.aroma || '')}</textarea>
        </div>
        <div class="form-group">
          <label for="taste">Sabor</label>
          <textarea id="taste">${escapeHtml(data?.taste || '')}</textarea>
        </div>
        <div class="form-group">
          <label for="finish">Final</label>
          <textarea id="finish">${escapeHtml(data?.finish || '')}</textarea>
        </div>
        <div class="form-group">
          <label for="overall_rating">Puntuación (1-100)</label>
          <input type="number" id="overall_rating" min="1" max="100" value="${data?.overall_rating || ''}">
        </div>
        <div class="form-group">
          <label for="notes">Notas Adicionales</label>
          <textarea id="notes">${escapeHtml(data?.notes || '')}</textarea>
        </div>
      `;
      break;
  }
  
  form.innerHTML = formContent + `
    <div class="form-actions">
      <button type="button" class="btn-cancel" onclick="closeModal()">Cancelar</button>
      <button type="submit" class="btn-submit">Guardar</button>
    </div>
  `;
  
  form.onsubmit = (e) => {
    e.preventDefault();
    saveItem(type);
  };
  
  modal.classList.add('active');
}

function closeModal() {
  const modal = document.getElementById('modal');
  modal.classList.remove('active');
  editingId = null;
  editingType = null;
}

// CRUD operations
async function saveItem(type) {
  const formData = new FormData(document.getElementById('modal-form'));
  const data = {};
  
  // Get all form inputs manually
  document.querySelectorAll('#modal-form input, #modal-form select, #modal-form textarea').forEach(input => {
    if (input.id) {
      let value = input.value;
      if (input.type === 'number' && value) {
        value = parseFloat(value);
      }
      if (value === '') value = null;
      data[input.id] = value;
    }
  });
  
  const endpoints = {
    'collector': 'collectors',
    'sommelier': 'sommeliers',
    'bottle': 'bottles',
    'aging': 'aging',
    'tasting': 'tastings'
  };
  
  const endpoint = endpoints[type];
  const method = editingId ? 'PUT' : 'POST';
  const url = editingId ? `${API_BASE}/${endpoint}/${editingId}` : `${API_BASE}/${endpoint}`;
  
  try {
    const response = await fetch(url, {
      method,
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(data)
    });
    
    if (!response.ok) {
      const error = await response.json();
      alert(error.error || 'Error al guardar');
      return;
    }
    
    closeModal();
    loadAllData();
  } catch (error) {
    alert('Error de conexión');
  }
}

async function editItem(type, id) {
  const endpoints = {
    'collector': 'collectors',
    'sommelier': 'sommeliers',
    'bottle': 'bottles',
    'aging': 'aging',
    'tasting': 'tastings'
  };
  
  const response = await fetch(`${API_BASE}/${endpoints[type]}/${id}`);
  const data = await response.json();
  showModal(type, data);
}

async function deleteItem(type, id) {
  if (!confirm('¿Está seguro de que desea eliminar este elemento?')) return;
  
  const endpoints = {
    'collector': 'collectors',
    'sommelier': 'sommeliers',
    'bottle': 'bottles',
    'aging': 'aging',
    'tasting': 'tastings'
  };
  
  try {
    await fetch(`${API_BASE}/${endpoints[type]}/${id}`, { method: 'DELETE' });
    loadAllData();
  } catch (error) {
    alert('Error al eliminar');
  }
}

// Utility function to prevent XSS
function escapeHtml(text) {
  if (text === null || text === undefined) return '';
  const div = document.createElement('div');
  div.textContent = String(text);
  return div.innerHTML;
}
