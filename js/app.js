const authSection = document.getElementById('authSection');
const authLayout = document.getElementById('authLayout');
const catalogSection = document.getElementById('catalogSection');
const plantCards = document.getElementById('plantCards');
const userInfo = document.getElementById('userInfo');
const searchInput = document.getElementById('searchInput');
const filterType = document.getElementById('filterType');

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
let currentUser = null;

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
  const formData = new FormData();
  Object.entries(data).forEach(([key, value]) => {
    formData.append(key, value);
  });

  try {
    const response = await fetch(endpoint, {
      method: 'POST',
      body: formData
    });
    return await response.json();
  } catch (error) {
    console.error('Fetch error:', error);
    return { success: false, message: 'Error de conexión' };
  }
}

async function handleRegister() {
  const name = document.getElementById('registerName').value.trim();
  const email = document.getElementById('registerEmail').value.trim();
  const password = document.getElementById('registerPassword').value.trim();
  const confirmPassword = document.getElementById('registerConfirmPassword').value.trim();

  if (password !== confirmPassword) {
    alert('Las contraseñas no coinciden.');
    return;
  }

  const result = await sendRequest('register.php', { name, email, password });
  if (result.success) {
    currentUser = result.user;
    updateUIAfterLogin();
    return;
  }

  alert(result.message || 'No se pudo registrar');
}

async function handleLogin() {
  const email = document.getElementById('loginEmail').value.trim();
  const password = document.getElementById('loginPassword').value.trim();

  const result = await sendRequest('login.php', { email, password });
  if (result.success) {
    currentUser = result.user;
    updateUIAfterLogin();
    return;
  }

  alert(result.message || 'Credenciales incorrectas');
}

async function handleForgot() {
  const email = document.getElementById('forgotEmail').value.trim();
  const result = await sendRequest('forgot_password.php', { email });

  if (result.success) {
    alert(result.message);
    showAuthSection(loginContainer);
    return;
  }

  alert(result.message || 'Error al enviar enlace');
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
    normalizedName.includes('orquídea') ||
    normalizedName.includes('orquidea') ||
    normalizedName.includes('tulip') ||
    normalizedName.includes('petunia') ||
    normalizedName.includes('begonia') ||
    normalizedName.includes('clavel') ||
    normalizedName.includes('geranio')
  ) {
    return 'plant-visual-bloom';
  }
  if (normalizedName.includes('helecho')) {
    return 'plant-visual-fern';
  }
  if (normalizedName.includes('bambú') || normalizedName.includes('bambu') || normalizedName.includes('yuca')) {
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

  const filtered = plants.filter((plant) => {
    const matchName = plant.name.toLowerCase().includes(query) || plant.care.toLowerCase().includes(query);
    const matchType = type === 'all' || plant.type === type;
    return matchName && matchType;
  });

  plantCards.innerHTML = filtered.map((plant) => `
    <article class="plant-card">
      <div class="plant-icon">${renderPlantIllustration(plant.type, plant.name)}</div>
      <h3>${plant.name}</h3>
      <span class="tag">${plant.type}</span>
      <p>${plant.care}</p>
      <button onclick="addPlant(${plant.id})">Agregar a mi colección</button>
    </article>
  `).join('');
}

window.addPlant = async function addPlant(plantId) {
  const result = await sendRequest('add_my_plant', { plant_id: plantId });
  if (result.success) {
    alert('Planta agregada a tu colección');
    return;
  }

  alert(result.message || 'No se pudo agregar la planta');
};

async function loadMyPlants() {
  const result = await sendRequest('get_my_plants');
  if (!result.success || typeof myPlantsList === 'undefined') return;

  myPlantsList.innerHTML = result.plants.map((plant) => `
    <article class="plant-card">
      <div class="plant-icon">${renderPlantIllustration(plant.type, plant.name)}</div>
      <h3>${plant.name}</h3>
      <span class="tag">${plant.type}</span>
      <p>${plant.care}</p>
    </article>
  `).join('');
}

window.handleLogin = handleLogin;
window.handleRegister = handleRegister;
window.handleForgot = handleForgot;
