const authSection = document.getElementById('authSection');
const authLayout = document.getElementById('authLayout');
const catalogSection = document.getElementById('catalogSection');
const catalogContent = document.getElementById('catalogContent');
const plantCards = document.getElementById('plantCards');
const userInfo = document.getElementById('userInfo');
const searchInput = document.getElementById('searchInput');
const filterType = document.getElementById('filterType');
const filterDifficulty = document.getElementById('filterDifficulty');
const myPlantsList = document.getElementById('myPlantsList');
const myPlantsEmpty = document.getElementById('myPlantsEmpty');
const myPlantsSummary = document.getElementById('myPlantsSummary');
const myPlantsCount = document.getElementById('myPlantsCount');
const agendaList = document.getElementById('agendaList');
const agendaEmpty = document.getElementById('agendaEmpty');
const agendaSummary = document.getElementById('agendaSummary');
const agendaDateLabel = document.getElementById('agendaDateLabel');
const agendaPanel = document.getElementById('agendaPanel');
const alertsSection = document.getElementById('alertsSection');
const alertsList = document.getElementById('alertsList');
const alertsEmpty = document.getElementById('alertsEmpty');
const alertsSummary = document.getElementById('alertsSummary');
const alertsBadge = document.getElementById('alertsBadge');
const recommendationsSection = document.getElementById('recommendationsSection');
const recommendationsList = document.getElementById('recommendationsList');
const recommendationsEmpty = document.getElementById('recommendationsEmpty');
const seasonalOverviewList = document.getElementById('seasonalOverviewList');
const seasonalOverviewEmpty = document.getElementById('seasonalOverviewEmpty');
const seasonalOverviewSummary = document.getElementById('seasonalOverviewSummary');
const currentSeasonBadge = document.getElementById('currentSeasonBadge');
const viewTodayCareButton = document.getElementById('viewTodayCareButton');
const closeAgendaButton = document.getElementById('closeAgendaButton');
const prevDayButton = document.getElementById('prevDayButton');
const nextDayButton = document.getElementById('nextDayButton');
const plantDetailModal = document.getElementById('plantDetailModal');
const plantDetailContent = document.getElementById('plantDetailContent');
const closePlantDetail = document.getElementById('closePlantDetail');
const toast = document.getElementById('toast');
const logoutButton = document.getElementById('logoutButton');
const toggleCatalogButton = document.getElementById('toggleCatalogButton');

const loginForm = document.getElementById('loginForm');
const registerForm = document.getElementById('registerForm');
const forgotForm = document.getElementById('forgotForm');
const loginContainer = document.getElementById('loginFormContainer');
const registerContainer = document.getElementById('registerFormContainer');
const forgotContainer = document.getElementById('forgotFormContainer');
const showRegister = document.getElementById('showRegister');
const showLogin = document.getElementById('showLogin');
const forgotLink = document.getElementById('forgotLink');
const backToLogin = document.getElementById('backToLogin');

let plants = [];
let myPlants = [];
let currentUser = null;
let toastTimeoutId = null;
let selectedAgendaDate = new Date();
let currentPlantDetail = null;

function showAuthSection(section) {
  loginContainer.classList.add('hidden');
  registerContainer.classList.add('hidden');
  forgotContainer.classList.add('hidden');
  section.classList.remove('hidden');
}

showRegister.addEventListener('click', (event) => {
  event.preventDefault();
  showAuthSection(registerContainer);
});

showLogin.addEventListener('click', (event) => {
  event.preventDefault();
  showAuthSection(loginContainer);
});

forgotLink.addEventListener('click', (event) => {
  event.preventDefault();
  showAuthSection(forgotContainer);
});

backToLogin.addEventListener('click', (event) => {
  event.preventDefault();
  showAuthSection(loginContainer);
});

logoutButton.addEventListener('click', handleLogout);
toggleCatalogButton.addEventListener('click', toggleCatalogContent);

if (viewTodayCareButton) {
  viewTodayCareButton.addEventListener('click', () => {
    selectedAgendaDate = new Date();
    showToast('Abriendo agenda del día...', 'success', 1800);

    const agendaPanelRef = document.getElementById('agendaPanel') || document.querySelector('.agenda-panel');
    if (agendaPanelRef) {
      agendaPanelRef.classList.remove('hidden');
    }

    if (catalogSection && catalogSection.classList.contains('hidden')) {
      catalogSection.classList.remove('hidden');
    }

    loadCareSchedule();

    if (agendaPanelRef) {
      agendaPanelRef.scrollIntoView({ behavior: 'smooth', block: 'start' });
      agendaPanelRef.classList.add('focus-ring');
      window.setTimeout(() => agendaPanelRef.classList.remove('focus-ring'), 1400);
    }
  });
}

if (closeAgendaButton) {
  closeAgendaButton.addEventListener('click', () => {
    if (agendaPanel) {
      agendaPanel.classList.add('hidden');
    }
    showToast('Agenda cerrada. Presiona "Ver cuidados del día" para volver a abrirla.', 'info', 3200);
  });
}

prevDayButton.addEventListener('click', () => changeAgendaDay(-1));
nextDayButton.addEventListener('click', () => changeAgendaDay(1));
closePlantDetail.addEventListener('click', closeDetailModal);
plantDetailModal.addEventListener('click', (event) => {
  if (event.target === plantDetailModal) {
    closeDetailModal();
  }
});

registerForm.addEventListener('submit', async (event) => {
  event.preventDefault();
  handleRegister();
});

forgotForm.addEventListener('submit', async (event) => {
  event.preventDefault();
  handleForgot();
});

searchInput.addEventListener('input', renderPlantCards);
filterType.addEventListener('change', renderPlantCards);
filterDifficulty.addEventListener('change', renderPlantCards);

async function sendRequest(endpoint, data = {}) {
  const target = endpoint.endsWith('.php') ? endpoint : 'api.php';
  const payload = endpoint.endsWith('.php') ? data : { ...data, action: endpoint };
  const formData = new FormData();

  Object.entries(payload).forEach(([key, value]) => {
    formData.append(key, value);
  });

  try {
    const response = await fetch(target, {
      method: 'POST',
      body: formData
    });
    return await response.json();
  } catch (error) {
    console.error('Fetch error:', error);
    return { success: false, message: 'Error de conexion' };
  }
}

function showToast(message, type = 'success', duration = 3200) {
  if (!toast) return;

  clearTimeout(toastTimeoutId);
  toast.textContent = message;
  toast.className = `toast toast-${type}`;
  toastTimeoutId = window.setTimeout(() => {
    toast.className = 'toast hidden';
  }, duration);
}

function toggleCatalogContent() {
  const isHidden = catalogContent.classList.toggle('hidden');
  toggleCatalogButton.textContent = isHidden ? 'Ver catalogo' : 'Ocultar catalogo';
  toggleCatalogButton.setAttribute('aria-expanded', String(!isHidden));
}

function formatDateValue(date) {
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, '0');
  const day = String(date.getDate()).padStart(2, '0');
  return `${year}-${month}-${day}`;
}

function formatAgendaDate(dateString) {
  const [year, month, day] = dateString.split('-').map(Number);
  const date = new Date(year, month - 1, day);
  return date.toLocaleDateString('es-AR', {
    weekday: 'long',
    day: 'numeric',
    month: 'long'
  });
}

function getCareIconClass(type) {
  const normalized = (type || '').toLowerCase();

  if (normalized.includes('riego')) return 'care-icon-water';
  if (normalized.includes('poda')) return 'care-icon-prune';
  if (normalized.includes('fertiliz')) return 'care-icon-feed';
  return 'care-icon-default';
}

function getRecommendationMessage(recommendation) {
  if (typeof recommendation === 'object' && recommendation !== null) {
    return recommendation.message || String(recommendation);
  }
  return String(recommendation);
}

function renderDifficultyBadge(difficulty) {
  if (!difficulty) return '';

  return `
    <span class="difficulty-badge difficulty-${difficulty.level}" title="${difficulty.experience}">
      Dificultad: ${difficulty.label}
    </span>
  `;
}

function renderDifficultyDetail(difficulty) {
  if (!difficulty) return '';

  return `
    <section class="difficulty-detail difficulty-detail-${difficulty.level}">
      <div class="difficulty-detail-head">
        <div>
          <span class="section-kicker">Nivel de dificultad</span>
          <h3>${difficulty.label}</h3>
        </div>
        <div class="difficulty-level-dots" aria-label="${difficulty.score} de 3">
          ${[1, 2, 3].map((level) => `<span class="${level <= difficulty.score ? 'active' : ''}"></span>`).join('')}
        </div>
      </div>
      <strong>${difficulty.experience}</strong>
      <p>${difficulty.description}</p>
      ${difficulty.reasons?.length ? `
        <ul>
          ${difficulty.reasons.map((reason) => `<li>${reason}</li>`).join('')}
        </ul>
      ` : ''}
    </section>
  `;
}

function renderSeasonalCare(seasonalCare, selectedSeason) {
  if (!seasonalCare || !seasonalCare.seasons) return '';

  const seasons = seasonalCare.seasons;
  const activeKey = seasons[selectedSeason]
    ? selectedSeason
    : seasonalCare.current_season;
  const activeSeason = seasons[activeKey];

  if (!activeSeason) return '';

  return `
    <section class="seasonal-care-section">
      <div class="seasonal-care-heading">
        <div>
          <span class="section-kicker">Cuidados por temporada</span>
          <h3>Cuidados estacionales</h3>
        </div>
        <span class="season-current-label">Hemisferio sur</span>
      </div>
      <div class="season-tabs" role="tablist" aria-label="Seleccionar temporada">
        ${Object.values(seasons).map((season) => `
          <button
            type="button"
            class="season-tab ${season.key === activeKey ? 'season-tab-active' : ''}"
            role="tab"
            aria-selected="${season.key === activeKey}"
            onclick="selectPlantSeason('${season.key}')"
          >
            ${season.name}
            ${season.is_current ? '<small>Actual</small>' : ''}
          </button>
        `).join('')}
      </div>
      <div class="season-care-panel" role="tabpanel">
        <div class="season-care-summary">
          <strong>${activeSeason.name}</strong>
          <p>${activeSeason.summary}</p>
        </div>
        <div class="season-care-grid">
          ${activeSeason.cares.map((care) => `
            <article class="season-care-item">
              <span>${care.category}</span>
              <p>${care.description}</p>
            </article>
          `).join('')}
        </div>
      </div>
    </section>
  `;
}

function renderSeasonalOverview() {
  if (!seasonalOverviewList) return;

  if (!myPlants.length) {
    seasonalOverviewList.innerHTML = '';
    seasonalOverviewEmpty?.classList.remove('hidden');
    currentSeasonBadge.textContent = 'Temporada actual';
    seasonalOverviewSummary.textContent = 'Consulta los cuidados adecuados para la estacion actual.';
    return;
  }

  const firstSeasonalCare = myPlants.find((plant) => plant.seasonal_care)?.seasonal_care;
  const currentKey = firstSeasonalCare?.current_season;
  const currentName = firstSeasonalCare?.seasons?.[currentKey]?.name || 'Temporada actual';

  seasonalOverviewEmpty?.classList.add('hidden');
  currentSeasonBadge.textContent = `${currentName} actual`;
  seasonalOverviewSummary.textContent = `Recomendaciones de ${currentName.toLowerCase()} para tus plantas.`;
  seasonalOverviewList.innerHTML = myPlants.map((plant) => {
    const seasonalCare = plant.seasonal_care;
    const season = seasonalCare?.seasons?.[seasonalCare.current_season];
    const watering = season?.cares?.find((care) => care.category === 'Riego');

    return `
      <article class="seasonal-overview-item">
        <div>
          <span class="tag">${plant.type}</span>
          <h3>${plant.name}</h3>
          <p>${watering?.description || season?.summary || 'Consulta sus cuidados estacionales.'}</p>
        </div>
        <button type="button" class="secondary-button compact-button" onclick="showPlantDetail(${plant.id})">
          Ver las 4 temporadas
        </button>
      </article>
    `;
  }).join('');
}

window.selectPlantSeason = function selectPlantSeason(seasonKey) {
  if (!currentPlantDetail?.seasonal_care) return;

  const container = document.getElementById('seasonalCareContainer');
  if (container) {
    container.innerHTML = renderSeasonalCare(currentPlantDetail.seasonal_care, seasonKey);
  }
};

async function logPlantEvent(plantId, type, event, details = '') {
  const result = await sendRequest('log_plant_event', {
    plant_id: plantId,
    type,
    event,
    details
  });

  if (result.success) {
    showToast('Historial actualizado.', 'success', 2800);
    await showPlantDetail(plantId);
    await loadMyPlants();
    return;
  }

  showToast(result.message || 'No se pudo registrar el evento.', 'error');
}

async function handleRegister() {
  const name = document.getElementById('registerName').value.trim();
  const email = document.getElementById('registerEmail').value.trim();
  const password = document.getElementById('registerPassword').value.trim();
  const confirmPassword = document.getElementById('registerConfirmPassword').value.trim();

  if (password !== confirmPassword) {
    showToast('Las contrasenas no coinciden.', 'error');
    return;
  }

  const result = await sendRequest('register.php', { name, email, password });
  if (result.success) {
    currentUser = result.user;
    updateUIAfterLogin();
    showToast('Cuenta creada con exito.');
    return;
  }

  showToast(result.message || 'No se pudo registrar', 'error');
}

async function handleLogin() {
  const email = document.getElementById('loginEmail').value.trim();
  const password = document.getElementById('loginPassword').value.trim();

  const result = await sendRequest('login.php', { email, password });
  if (result.success) {
    currentUser = result.user;
    updateUIAfterLogin();
    showToast('Sesion iniciada.');
    return;
  }

  showToast(result.message || 'Credenciales incorrectas', 'error');
}

async function handleForgot() {
  const email = document.getElementById('forgotEmail').value.trim();
  const result = await sendRequest('forgot_password.php', { email });

  if (result.success) {
    showToast(result.message);
    showAuthSection(loginContainer);
    return;
  }

  showToast(result.message || 'Error al enviar enlace', 'error');
}

function updateUIAfterLogin() {
  if (!currentUser) return;

  authLayout.classList.add('hidden');
  authSection.classList.add('hidden');
  catalogSection.classList.remove('hidden');
  document.body.classList.remove('auth-mode');
  document.body.classList.add('catalog-mode');
  userInfo.textContent = `Hola, ${currentUser.name}`;
  loadPlants();
  loadMyPlants();
}

function resetCatalogState() {
  plants = [];
  myPlants = [];
  currentUser = null;
  userInfo.textContent = '';
  plantCards.innerHTML = '';
  catalogContent.classList.add('hidden');
  toggleCatalogButton.textContent = 'Ver catalogo';
  toggleCatalogButton.setAttribute('aria-expanded', 'false');
  myPlantsList.innerHTML = '';
  agendaList.innerHTML = '';
  myPlantsSummary.textContent = 'Todavia no agregaste plantas a tu coleccion.';
  myPlantsCount.textContent = '0';
  myPlantsEmpty.classList.remove('hidden');
  agendaSummary.textContent = 'Organiza riego, poda y fertilizacion segun tus plantas.';
  agendaEmpty.classList.remove('hidden');
  searchInput.value = '';
  filterType.value = 'all';
  filterDifficulty.value = 'all';
  selectedAgendaDate = new Date();
}

function showLoggedOutUI() {
  resetCatalogState();
  catalogSection.classList.add('hidden');
  authLayout.classList.remove('hidden');
  authSection.classList.remove('hidden');
  document.body.classList.remove('catalog-mode');
  document.body.classList.add('auth-mode');
  showAuthSection(loginContainer);
}

async function handleLogout() {
  logoutButton.disabled = true;
  showLoggedOutUI();
  showToast('Sesion cerrada.', 'success', 500);

  const result = await sendRequest('logout');
  logoutButton.disabled = false;

  if (result.success) return;

  showToast(result.message || 'Se cerro la sesion local.', 'error', 900);
}

async function loadPlants() {
  const result = await sendRequest('get_plants');
  if (!result.success) return;

  plants = result.plants || [];
  renderPlantCards();
}

function getPlantIllustrationVariant(type, name) {
  const normalizedName = (name || '').toLowerCase();

  if (normalizedName.includes('cactus') || normalizedName.includes('aloe')) {
    return 'plant-visual-cactus';
  }
  if (normalizedName.includes('hortensia')) {
    return 'plant-visual-hydrangea';
  }
  if (normalizedName.includes('lavanda')) {
    return 'plant-visual-lavender';
  }
  if (normalizedName.includes('lirio')) {
    return 'plant-visual-lily';
  }
  if (normalizedName.includes('margarita')) {
    return 'plant-visual-daisy';
  }
  if (
    normalizedName.includes('rosa') ||
    normalizedName.includes('orquidea') ||
    normalizedName.includes('petunia') ||
    normalizedName.includes('begonia') ||
    normalizedName.includes('clavel') ||
    normalizedName.includes('geranio') ||
    normalizedName.includes('tulip')
  ) {
    return 'plant-visual-bloom';
  }
  if (normalizedName.includes('helecho')) {
    return 'plant-visual-fern';
  }
  if (normalizedName.includes('bambu') || normalizedName.includes('yuca')) {
    return 'plant-visual-stem';
  }
  if (normalizedName.includes('monstera') || normalizedName.includes('pothos') || normalizedName.includes('ficus')) {
    return 'plant-visual-wide';
  }

  return type === 'exterior' ? 'plant-visual-outdoor' : 'plant-visual-indoor';
}

function renderPlantIllustration(type, name) {
  const variant = getPlantIllustrationVariant(type, name);
  return `
    <div class="plant-visual ${variant}" aria-hidden="true">
      <div class="plant-visual-glow"></div>
      <div class="plant-visual-leaf plant-visual-leaf-left"></div>
      <div class="plant-visual-leaf plant-visual-leaf-center"></div>
      <div class="plant-visual-leaf plant-visual-leaf-right"></div>
      <div class="plant-visual-pot"></div>
    </div>
  `;
}

function renderPlantCards() {
  const query = searchInput.value.toLowerCase();
  const type = filterType.value;
  const difficulty = filterDifficulty.value;
  const ownedPlantIds = new Set(myPlants.map((plant) => Number(plant.id)));

  const filtered = plants.filter((plant) => {
    const matchName = plant.name.toLowerCase().includes(query) || plant.care.toLowerCase().includes(query);
    const matchType = type === 'all' || plant.type === type;
    const matchDifficulty = difficulty === 'all' || plant.difficulty?.level === difficulty;
    return matchName && matchType && matchDifficulty;
  });

  if (!filtered.length) {
    plantCards.innerHTML = `
      <article class="empty-card">
        <h3>No encontramos plantas</h3>
        <p>Prueba con otro nombre o cambia el filtro para ver mas opciones.</p>
      </article>
    `;
    return;
  }

  plantCards.innerHTML = filtered.map((plant) => `
    <article class="plant-card">
      <div class="plant-icon">${renderPlantIllustration(plant.type, plant.name)}</div>
      <h3>${plant.name}</h3>
      <div class="plant-badges">
        <span class="tag">${plant.type}</span>
        ${renderDifficultyBadge(plant.difficulty)}
      </div>
      <p>${plant.care}</p>
      <div class="plant-card-actions">
        <button type="button" onclick="showPlantDetail(${plant.id})">Ver detalle</button>
        <button type="button" onclick="addPlant(${plant.id})" ${ownedPlantIds.has(Number(plant.id)) ? 'disabled' : ''}>
          ${ownedPlantIds.has(Number(plant.id)) ? 'Ya esta en tu coleccion' : 'Agregar a mi coleccion'}
        </button>
      </div>
    </article>
  `).join('');
}

window.addPlant = async function addPlant(plantId) {
  const result = await sendRequest('add_my_plant', { plant_id: plantId });
  if (result.success) {
    showToast('Planta agregada a tu coleccion');
    await loadMyPlants();
    myPlantsList.scrollTo({ top: 0, behavior: 'smooth' });
    return;
  }

  showToast(result.message || 'No se pudo agregar la planta', 'error');
};

window.removePlant = async function removePlant(plantId) {
  const result = await sendRequest('remove_my_plant', { plant_id: plantId });
  if (result.success) {
    showToast('Planta eliminada de tu coleccion');
    await loadMyPlants();
    return;
  }

  showToast(result.message || 'No se pudo eliminar la planta', 'error');
};

async function loadMyPlants() {
  const result = await sendRequest('get_my_plants');
  if (!result.success || !myPlantsList) return;

  myPlants = result.plants || [];
  await loadCareSchedule();
  await loadAlerts();
  await loadRecommendations();

  if (!myPlants.length) {
    myPlantsList.innerHTML = '';
    myPlantsEmpty.classList.remove('hidden');
    myPlantsSummary.textContent = 'Todavia no agregaste plantas a tu coleccion.';
    myPlantsCount.textContent = '0';
    renderSeasonalOverview();
    renderPlantCards();
    return;
  }

  myPlantsEmpty.classList.add('hidden');
  myPlantsSummary.textContent = `${myPlants.length} planta${myPlants.length === 1 ? '' : 's'} en seguimiento.`;
  myPlantsCount.textContent = String(myPlants.length);

  myPlantsList.innerHTML = myPlants.map((plant) => `
    <article class="collection-item">
      <button type="button" class="collection-thumb collection-thumb-button" onclick="showPlantDetail(${plant.id})" aria-label="Ver detalle de ${plant.name}">
        ${renderPlantIllustration(plant.type, plant.name)}
      </button>
      <div class="collection-body">
        <h3><button type="button" class="link-button" onclick="showPlantDetail(${plant.id})">${plant.name}</button></h3>
        <div class="collection-meta">
          <span class="tag">${plant.type}</span>
          ${renderDifficultyBadge(plant.difficulty)}
        </div>
      </div>
      <div class="collection-side">
        <span class="status-pill ${plant.status === 'OK' ? 'status-ok' : 'status-attention'}">${plant.status || 'OK'}</span>
        <span class="status-detail">${plant.status_detail || 'Sin tareas pendientes hoy'}</span>
        ${plant.recommendations && plant.recommendations.length ? `<span class="recommendation-text">${getRecommendationMessage(plant.recommendations[0])}</span>` : ''}
        <div class="collection-side-actions">
          <button type="button" class="collection-detail-button compact-button" onclick="showPlantDetail(${plant.id})">Ver cuidados</button>
          <button type="button" class="collection-remove" onclick="removePlant(${plant.id})">Quitar</button>
        </div>
      </div>
    </article>
  `).join('');

  renderSeasonalOverview();
  renderPlantCards();
}

async function loadCareSchedule() {
  if (!agendaList) return;

  const date = formatDateValue(selectedAgendaDate);
  agendaDateLabel.textContent = formatAgendaDate(date);
  agendaDateLabel.dateTime = date;

  const result = await sendRequest('get_care_schedule', { date });
  if (!result.success) {
    agendaList.innerHTML = '';
    agendaEmpty.classList.remove('hidden');
    agendaEmpty.textContent = result.message || 'No se pudo cargar la agenda.';
    return;
  }

  const tasks = result.tasks || [];
  const today = formatDateValue(new Date());

  agendaSummary.textContent = date === today
    ? 'Tareas destacadas para hoy.'
    : 'Cuidados programados para la fecha seleccionada.';

  if (!tasks.length) {
    agendaList.innerHTML = '';
    agendaEmpty.textContent = 'No hay cuidados pendientes para este dia.';
    agendaEmpty.classList.remove('hidden');
    return;
  }

  agendaEmpty.classList.add('hidden');
  agendaList.innerHTML = tasks.map((task) => `
    <article class="agenda-item ${task.is_today ? 'agenda-item-today' : ''}">
      <div class="care-icon ${getCareIconClass(task.type)}" aria-hidden="true"></div>
      <div class="agenda-item-body">
        <h3>${task.type}</h3>
        <p>${task.plant_name} · ${task.description}</p>
        <span>Frecuencia: cada ${task.frequency_days} dia${Number(task.frequency_days) === 1 ? '' : 's'}</span>
      </div>
      <button type="button" class="agenda-plant-link" onclick="showPlantDetail(${task.plant_id})">Detalle</button>
    </article>
  `).join('');
}

function formatAlertDate(dateString) {
  const today = formatDateValue(new Date());
  if (dateString === today) {
    return 'Hoy';
  }
  return formatAgendaDate(dateString);
}

async function loadAlerts() {
  if (!alertsList) return;

  const result = await sendRequest('get_alerts');
  const tasks = result.success ? result.tasks || [] : [];
  const todayCount = tasks.filter((task) => task.is_today).length;
  const upcomingCount = tasks.filter((task) => !task.is_today).length;

  if (!result.success) {
    alertsList.innerHTML = '';
    alertsEmpty.classList.remove('hidden');
    alertsEmpty.textContent = result.message || 'No se pudieron cargar las alertas de cuidado.';
    alertsBadge.classList.add('hidden');
    return;
  }

  alertsEmpty.classList.add('hidden');
  alertsBadge.classList.remove('hidden');
  alertsBadge.textContent = `${todayCount} hoy · ${upcomingCount} proximas`;
  alertsSummary.textContent = todayCount
    ? `Tienes ${todayCount} alerta${todayCount === 1 ? '' : 's'} para hoy y ${upcomingCount} proximas.`
    : upcomingCount
      ? `No hay tareas para hoy. Hay ${upcomingCount} proximas en la agenda.`
      : 'No hay alertas de cuidado por ahora. Tu coleccion esta en buen momento.';

  if (!tasks.length) {
    alertsList.innerHTML = '';
    alertsBadge.classList.add('hidden');
    alertsEmpty.classList.remove('hidden');
    return;
  }

  alertsList.innerHTML = tasks.map((task) => `
    <article class="alert-item ${task.is_today ? 'alert-item-today' : ''}">
      <div class="alert-meta">
        <strong>${task.type}</strong>
        <span class="alert-plant">${task.plant_name}</span>
      </div>
      <div class="alert-detail">
        <span>${formatAlertDate(task.date)}</span>
        <span class="alert-pill">Cada ${task.frequency_days} dia${Number(task.frequency_days) === 1 ? '' : 's'}</span>
      </div>
      <button type="button" class="agenda-plant-link" onclick="showPlantDetail(${task.plant_id})">Ver</button>
    </article>
  `).join('');

  if (todayCount > 0) {
    showToast(`Tienes ${todayCount} alerta${todayCount === 1 ? '' : 's'} para hoy.`, 'success', 3600);
  }
}

async function loadRecommendations() {
  if (!recommendationsList) return;

  const result = await sendRequest('get_recommendations');
  const recommendations = result.success ? result.recommendations || [] : [];

  if (!recommendations.length) {
    recommendationsSection?.classList?.add('hidden');
    recommendationsList.innerHTML = '';
    recommendationsEmpty.classList.remove('hidden');
    return;
  }

  recommendationsSection?.classList?.remove('hidden');
  recommendationsEmpty.classList.add('hidden');
  recommendationsList.innerHTML = recommendations.map((recommendation) => {
    const severityClass = recommendation.severity ? ` recommendation-${recommendation.severity}` : '';
    const icon = recommendation.severity === 'urgent'
      ? '🔥'
      : recommendation.severity === 'warning'
        ? '⚠️'
        : '💡';
    const message = getRecommendationMessage(recommendation);

    return `
      <article class="recommendation-item${severityClass}">
        <div class="recommendation-text-icon" aria-hidden="true">${icon}</div>
        <div class="recommendation-body">
          <div class="recommendation-meta">
            <strong>${recommendation.plant_name}</strong>
            <span class="recommendation-pill recommendation-pill-${recommendation.severity}">${recommendation.severity_text}</span>
          </div>
          <p>${message}</p>
        </div>
        <button type="button" class="agenda-plant-link" onclick="showPlantDetail(${recommendation.plant_id})">Ver</button>
      </article>
    `;
  }).join('');
}

function changeAgendaDay(offset) {
  selectedAgendaDate.setDate(selectedAgendaDate.getDate() + offset);
  loadCareSchedule();
}

window.showPlantDetail = async function showPlantDetail(plantId) {
  const localPlant = myPlants.find((plant) => Number(plant.id) === Number(plantId));
  const result = await sendRequest('get_plant_cares', { plant_id: plantId });

  if (!result.success) {
    showToast(result.message || 'No se pudo abrir el detalle', 'error');
    return;
  }

  const plant = result.plant || localPlant;
  const cares = result.cares || [];
  currentPlantDetail = plant;

  plantDetailContent.innerHTML = `
    <div class="plant-detail-head">
      <div class="collection-thumb">${renderPlantIllustration(plant.type, plant.name)}</div>
      <div>
        <span class="section-kicker">Detalle</span>
        <h2 id="plantDetailTitle">${plant.name}</h2>
        <div class="collection-meta">
          <span class="tag">${plant.type}</span>
          ${renderDifficultyBadge(plant.difficulty)}
          <span class="status-pill ${plant.status === 'OK' ? 'status-ok' : 'status-attention'}">${plant.status || 'OK'}</span>
        </div>
      </div>
    </div>
    <p class="plant-detail-care">${plant.care}</p>
    ${renderDifficultyDetail(plant.difficulty)}
    <div id="seasonalCareContainer">
      ${renderSeasonalCare(plant.seasonal_care, plant.seasonal_care?.current_season)}
    </div>
    <section class="plant-detail-actions">
      <h3>Registrar acción rápida</h3>
      <div class="plant-action-buttons">
        <button type="button" class="secondary-button compact-button" onclick="logPlantEvent(${plant.id}, 'Riego', 'Riego registrado')">Riego</button>
        <button type="button" class="secondary-button compact-button" onclick="logPlantEvent(${plant.id}, 'Poda', 'Poda realizada')">Poda</button>
        <button type="button" class="secondary-button compact-button" onclick="logPlantEvent(${plant.id}, 'Fertilización', 'Fertilización aplicada')">Fertilización</button>
        <button type="button" class="secondary-button compact-button" onclick="logPlantEvent(${plant.id}, 'Estado', 'Actualización de estado de salud')">Estado</button>
      </div>
    </section>
    ${plant.recommendations && plant.recommendations.length ? `
      <section class="plant-recommendation-section">
        <h3>Recomendaciones</h3>
        <ul class="plant-recommendation-list">
          ${plant.recommendations.map((recommendation) => `<li>${getRecommendationMessage(recommendation)}</li>`).join('')}
        </ul>
      </section>
    ` : ''}
    <section class="plant-history-section">
      <h3>Historial de cuidados</h3>
      <div class="care-history-list">
        ${plant.history && plant.history.length ? plant.history.map((entry) => `
          <article class="care-history-item">
            <div class="history-marker"></div>
            <div>
              <div class="history-header">
                <strong>${entry.tipo}</strong>
                <span>${formatAgendaDate(entry.fecha.slice(0, 10))}</span>
              </div>
              <p>${entry.evento}${entry.detalles ? ` — ${entry.detalles}` : ''}</p>
            </div>
          </article>
        `).join('') : '<p class="empty-state">Aún no hay historial de cuidados para esta planta.</p>'}
      </div>
    </section>
    <h3>Proximos cuidados</h3>
    <div class="detail-care-list">
      ${cares.length ? cares.map((care) => `
        <article class="detail-care-item">
          <div class="detail-care-meta">
            <div>
              <strong>${care.type}</strong>
              <span>${formatAgendaDate(care.date)}</span>
            </div>
            <small>Cada ${care.frequency_days} dia${Number(care.frequency_days) === 1 ? '' : 's'}</small>
          </div>
          <p>${care.description}</p>
        </article>
      `).join('') : '<p class="empty-state">No hay cuidados proximos registrados.</p>'}
    </div>
  `;

  plantDetailModal.classList.remove('hidden');
};

function closeDetailModal() {
  plantDetailModal.classList.add('hidden');
}

async function checkSession() {
  const result = await sendRequest('check_session');
  if (!result.success || !result.user) return;

  currentUser = result.user;
  updateUIAfterLogin();
}

checkSession();

window.handleLogin = handleLogin;
window.handleRegister = handleRegister;
window.handleForgot = handleForgot;
