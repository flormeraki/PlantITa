const authSection = document.getElementById('authSection');
const authLayout = document.getElementById('authLayout');
const catalogSection = document.getElementById('catalogSection');
const plantCards = document.getElementById('plantCards');
const userInfo = document.getElementById('userInfo');
const searchInput = document.getElementById('searchInput');
const filterType = document.getElementById('filterType');
const myPlantsList = document.getElementById('myPlantsList');
const myPlantsEmpty = document.getElementById('myPlantsEmpty');
const myPlantsSummary = document.getElementById('myPlantsSummary');
const myPlantsCount = document.getElementById('myPlantsCount');
const toast = document.getElementById('toast');
const logoutButton = document.getElementById('logoutButton');

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
  myPlantsList.innerHTML = '';
  myPlantsSummary.textContent = 'Todavia no agregaste plantas a tu coleccion.';
  myPlantsCount.textContent = '0';
  myPlantsEmpty.classList.remove('hidden');
  searchInput.value = '';
  filterType.value = 'all';
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
  const result = await sendRequest('get_plantas.php');
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
  const ownedPlantIds = new Set(myPlants.map((plant) => Number(plant.id)));

  const filtered = plants.filter((plant) => {
    const matchName = plant.name.toLowerCase().includes(query) || plant.care.toLowerCase().includes(query);
    const matchType = type === 'all' || plant.type === type;
    return matchName && matchType;
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
      <span class="tag">${plant.type}</span>
      <p>${plant.care}</p>
      <button onclick="addPlant(${plant.id})" ${ownedPlantIds.has(Number(plant.id)) ? 'disabled' : ''}>
        ${ownedPlantIds.has(Number(plant.id)) ? 'Ya esta en tu coleccion' : 'Agregar a mi coleccion'}
      </button>
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

  if (!myPlants.length) {
    myPlantsList.innerHTML = '';
    myPlantsEmpty.classList.remove('hidden');
    myPlantsSummary.textContent = 'Todavia no agregaste plantas a tu coleccion.';
    myPlantsCount.textContent = '0';
    renderPlantCards();
    return;
  }

  myPlantsEmpty.classList.add('hidden');
  myPlantsSummary.textContent = `${myPlants.length} planta${myPlants.length === 1 ? '' : 's'} en seguimiento.`;
  myPlantsCount.textContent = String(myPlants.length);

  myPlantsList.innerHTML = myPlants.map((plant) => `
    <article class="collection-item">
      <div class="collection-thumb">${renderPlantIllustration(plant.type, plant.name)}</div>
      <div class="collection-body">
        <h3>${plant.name}</h3>
        <div class="collection-meta">
          <span class="tag">${plant.type}</span>
          <span class="collection-care">${plant.care}</span>
        </div>
      </div>
      <div class="collection-side">
        <span class="status-pill">${plant.status || 'Activa'}</span>
        <button type="button" class="collection-remove" onclick="removePlant(${plant.id})">Quitar</button>
      </div>
    </article>
  `).join('');

  renderPlantCards();
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
