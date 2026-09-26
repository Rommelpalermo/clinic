let visits = [
  { name: "Maria Santos", reason: "head acne", severity: "mild", date: "May 4", fullDate: "May 4, 2026", dx: "tension and stress", status: "followup" },
  { name: "Maria Santos", reason: "Headache and dizziness", severity: "mild", date: "May 4", fullDate: "May 4, 2026", dx: "Tension headache", status: "completed" },
  { name: "Juan Dela Cruz", reason: "Fever and body malaise", severity: "moderate", date: "May 4", fullDate: "May 4, 2026", dx: "Viral infection", status: "completed" },
  { name: "Patricia Mendoza", reason: "Minor cut on hand", severity: "mild", date: "May 4", fullDate: "May 4, 2026", dx: "Superficial laceration", status: "completed" },
  { name: "Maria Santos", reason: "Asthma attack during PE class", severity: "severe", date: "May 4", fullDate: "May 4, 2026", dx: "Acute asthma exacerbation", status: "completed" },
  { name: "Carlos Garcia", reason: "High blood pressure episode", severity: "severe", date: "May 4", fullDate: "May 4, 2026", dx: "Hypertension exacerbation", status: "followup" },
  { name: "Angela Reyes", reason: "Cough and cold symptoms", severity: "mild", date: "May 4", fullDate: "May 4, 2026", dx: "Upper respiratory tract infection", status: "completed" },
  { name: "Gladezel Aubrey Anulat", reason: "Sprained ankle", severity: "moderate", date: "May 4", fullDate: "May 4, 2026", dx: "Ankle sprain", status: "completed" },
];

let lowStock = [
  { name: "Isopropyl Alcohol 70%", type: "Consumable", qty: 3, min: 5 },
  { name: "Disposable Gloves (Medium)", type: "Ppe", qty: 8, min: 10 },
  { name: "Adhesive Bandages", type: "First Aid", qty: 15, min: 20 },
];

let inventory = [
  { name: "Isopropyl Alcohol 70%", supplier: "Metro Drug Inc.", category: "Consumable", qty: 3, unit: "bottles", expiry: "Apr 1, 2027", warn: false, status: "lowstock" },
  { name: "Digital Thermometer", supplier: "TechMed Corp.", category: "Equipment", qty: 5, unit: "pieces", expiry: "—", warn: false, status: "instock" },
  { name: "Face Masks (Surgical)", supplier: "SafeGuard Med", category: "Ppe", qty: 45, unit: "boxes", expiry: "Aug 1, 2027", warn: false, status: "instock" },
  { name: "Oral Rehydration Salts", supplier: "Metro Drug Inc.", category: "Medication", qty: 60, unit: "packs", expiry: "Dec 1, 2026", warn: true, status: "instock" },
  { name: "Gauze Rolls", supplier: "MedSupply PH", category: "First Aid", qty: 30, unit: "rolls", expiry: "Dec 1, 2027", warn: false, status: "instock" },
  { name: "Disposable Gloves (Medium)", supplier: "SafeGuard Med", category: "Ppe", qty: 8, unit: "boxes", expiry: "Sep 1, 2027", warn: false, status: "lowstock" },
  { name: "Adhesive Bandages", supplier: "MedSupply PH", category: "First Aid", qty: 15, unit: "boxes", expiry: "Jan 1, 2028", warn: false, status: "lowstock" },
  { name: "Blood Pressure Monitor", supplier: "TechMed Corp.", category: "Equipment", qty: 3, unit: "pieces", expiry: "—", warn: false, status: "instock" },
  { name: "Paracetamol 500mg", supplier: "Metro Drug Inc.", category: "Medication", qty: 250, unit: "pieces", expiry: "Jun 15, 2027", warn: false, status: "instock" },
  { name: "Ibuprofen 200mg", supplier: "Metro Drug Inc.", category: "Medication", qty: 120, unit: "pieces", expiry: "Mar 20, 2027", warn: false, status: "instock" },
];

let inventoryValue = 21475;

let patients = [
  { id: "20-0190", name: "Gladezel Aubrey Anulat", course: "Btte-FSM", year: "1st Year", blood: "Unknown", status: "active" },
  { id: "TCI-2024-0003", name: "Angela Reyes", course: "BS Computer Science", year: "1st Year", blood: "B+", status: "active" },
  { id: "TCI-2023-0015", name: "Carlos Garcia", course: "BS Accountancy", year: "4th Year", blood: "AB+", status: "active" },
  { id: "TCI-2023-0020", name: "Patricia Mendoza", course: "BS Nursing", year: "3rd Year", blood: "O-", status: "active" },
  { id: "TCI-2024-0002", name: "Juan Dela Cruz", course: "BS Business Administration", year: "3rd Year", blood: "A+", status: "active" },
  { id: "TCI-2024-0001", name: "Maria Santos", course: "BS Information Technology", year: "2nd Year", blood: "O+", status: "active" },
];

function escapeHtml(value) {
  return String(value ?? "").replace(/[&<>"']/g, character => ({
    "&": "&amp;",
    "<": "&lt;",
    ">": "&gt;",
    '"': "&quot;",
    "'": "&#39;",
  })[character]);
}

async function apiRequest(payload) {
  const options = payload
    ? { method: "POST", headers: { "Content-Type": "application/json" }, body: JSON.stringify(payload) }
    : {};
  const response = await fetch("api.php", options);
  const result = await response.json();
  if (!response.ok) throw new Error(result.error || "The database request failed.");
  return result;
}

async function loadClinicData() {
  try {
    const data = await apiRequest();
    patients = data.patients;
    visits = data.visits;
    inventory = data.inventory;
    lowStock = inventory.filter(item => item.status === "lowstock").map(item => ({
      name: item.name,
      type: item.category,
      qty: item.qty,
      min: item.min,
    }));
    inventoryValue = Number(data.inventoryValue);
    renderVisits();
    renderStock();
    renderPatients(patients);
    renderVisitRecords(visits);
    renderInventory(inventory);
    renderAnalytics();
    updateInventorySummary();
    updateVisitSummary();
  } catch (error) {
    console.error("Could not load clinic data from MySQL:", error);
  }
}

function initials(name) {
  return name.split(" ").map(w => w[0]).slice(0, 2).join("").toUpperCase();
}

function renderVisits() {
  const list = document.getElementById("visitList");
  list.innerHTML = visits.map(v => `
    <li>
      <div class="visit-left">
        <div class="visit-avatar">${escapeHtml(initials(v.name))}</div>
        <div>
          <div class="visit-name">${escapeHtml(v.name)}</div>
          <div class="visit-reason">${escapeHtml(v.reason)}</div>
        </div>
      </div>
      <div class="visit-right">
        <span class="badge badge-${escapeHtml(v.severity)}">${escapeHtml(v.severity)}</span>
        <span class="visit-date">🕐 ${escapeHtml(v.date)}</span>
      </div>
    </li>
  `).join("");
}

function renderStock() {
  const list = document.getElementById("stockList");
  list.innerHTML = lowStock.map(s => `
    <li>
      <div>
        <div class="stock-name">${escapeHtml(s.name)}</div>
        <div class="stock-type">${escapeHtml(s.type)}</div>
      </div>
      <div class="stock-right">
        <div class="stock-qty">${escapeHtml(s.qty)}</div>
        <div class="stock-min">min: ${escapeHtml(s.min)}</div>
      </div>
    </li>
  `).join("");
}

function renderPatients(list) {
  const body = document.getElementById("patientTableBody");
  body.innerHTML = list.map(p => `
    <tr>
      <td>${escapeHtml(p.id)}</td>
      <td><a href="#" class="patient-link">${escapeHtml(p.name)}</a></td>
      <td>${escapeHtml(p.course)}</td>
      <td>${escapeHtml(p.year)}</td>
      <td>${escapeHtml(p.blood)}</td>
      <td><span class="status-${escapeHtml(p.status)}">${escapeHtml(p.status)}</span></td>
      <td><a href="#" class="edit-link">Edit</a></td>
    </tr>
  `).join("");
  document.getElementById("patientCount").textContent = `${patients.length} registered patients`;
}

function renderVisitRecords(list) {
  const body = document.getElementById("visitRecordList");
  body.innerHTML = list.map(v => `
    <li>
      <div class="visit-record-main">
        <div class="visit-record-top">
          <span class="visit-record-name">${escapeHtml(v.name)}</span>
          <span class="badge badge-${escapeHtml(v.severity)}">${escapeHtml(v.severity)}</span>
              <span class="badge badge-${escapeHtml(v.status)}">${escapeHtml(({ followup: "follow up needed", completed: "completed", inprogress: "in progress" })[v.status] || v.status)}</span>
        </div>
        <div class="visit-record-reason">${escapeHtml(v.reason)}</div>
        <div class="visit-record-dx">Dx: ${escapeHtml(v.dx)}</div>
      </div>
      <span class="visit-record-date">🕐 ${escapeHtml(v.fullDate)}</span>
    </li>
  `).join("");
  document.getElementById("visitCount").textContent = `${visits.length} total visits recorded`;
}

function renderInventory(list) {
  const body = document.getElementById("inventoryTableBody");
  body.innerHTML = list.map(item => `
    <tr>
      <td>
        <div class="item-name">${escapeHtml(item.name)}</div>
        <div class="item-supplier">${escapeHtml(item.supplier)}</div>
      </td>
      <td>${escapeHtml(item.category)}</td>
      <td><span class="qty-value ${item.status === "lowstock" ? "qty-low" : ""}">${escapeHtml(item.qty)}</span> ${escapeHtml(item.unit)}</td>
      <td class="${item.warn ? "expiry-warning" : ""}">${item.warn ? "⚠ " : ""}${escapeHtml(item.expiry)}</td>
      <td><span class="status-${item.status}">${item.status === "lowstock" ? "low stock" : "in stock"}</span></td>
      <td><a href="#" class="edit-link">Edit</a></td>
    </tr>
  `).join("");
  document.getElementById("inventoryCount").textContent = `${inventory.length} medical supplies tracked`;
}

function updateInventorySummary() {
  const lowStockCount = inventory.filter(item => item.status === "lowstock").length;
  document.getElementById("statInventory").textContent = inventory.length;
  document.getElementById("statLowStock").textContent = lowStockCount;
  document.getElementById("analyticsInventoryValue").textContent = `₱${inventoryValue.toLocaleString("en-PH")}`;
}

function filterInventory() {
  const q = document.getElementById("inventorySearchInput").value.toLowerCase();
  const category = document.getElementById("categoryFilter").value;
  const filtered = inventory.filter(item =>
    (category === "all" || item.category === category) &&
    item.name.toLowerCase().includes(q)
  );
  renderInventory(filtered);
}

function renderLineChart(containerId, labels, values) {
  const w = 560, h = 220, padL = 30, padB = 26, padT = 16, padR = 10;
  const max = Math.max(...values, 1);
  const stepX = (w - padL - padR) / (labels.length - 1);
  const points = values.map((v, i) => {
    const x = padL + i * stepX;
    const y = padT + (h - padT - padB) * (1 - v / max);
    return { x, y };
  });
  const gridLines = [0, 2, 4, 6, 8].filter(v => v <= max || v === 0)
    .map(v => {
      const y = padT + (h - padT - padB) * (1 - v / max);
      return `<line class="grid-line" x1="${padL}" y1="${y}" x2="${w - padR}" y2="${y}"></line>
              <text class="axis-label" x="${padL - 8}" y="${y + 4}" text-anchor="end">${v}</text>`;
    }).join("");
  const labelEls = labels.map((lbl, i) =>
    `<text class="axis-label" x="${points[i].x}" y="${h - 6}" text-anchor="middle">${lbl}</text>`
  ).join("");
  const linePath = points.map(p => `${p.x},${p.y}`).join(" ");
  const dots = points.map(p => `<circle class="trend-dot" cx="${p.x}" cy="${p.y}" r="4"></circle>`).join("");
  document.getElementById(containerId).innerHTML = `
    <svg class="line-chart-svg" viewBox="0 0 ${w} ${h}">
      ${gridLines}
      ${labelEls}
      <polyline class="trend-line" points="${linePath}"></polyline>
      ${dots}
    </svg>
  `;
}

function renderDonut(donutId, legendId, data) {
  const total = data.reduce((sum, d) => sum + d.value, 0) || 1;
  let cursor = 0;
  const stops = data.map(d => {
    const start = (cursor / total) * 360;
    cursor += d.value;
    const end = (cursor / total) * 360;
    return `${d.color} ${start}deg ${end}deg`;
  }).join(", ");
  document.getElementById(donutId).style.background = `conic-gradient(${stops})`;
  document.getElementById(legendId).innerHTML = data.map(d => `
    <li><span class="legend-dot" style="background:${d.color}"></span>${d.label} ${Math.round((d.value / total) * 100)}%</li>
  `).join("");
}

function renderBarChart(listId, data) {
  const max = Math.max(...data.map(d => d.count), 4);
  document.getElementById(listId).innerHTML = data.map(d => `
    <li class="bar-row">
      <span class="bar-label">${d.label}</span>
      <div class="bar-track"><div class="bar-fill" style="width:${(d.count / max) * 100}%"></div></div>
    </li>
  `).join("");
}

function renderAnalytics() {
  document.getElementById("analyticsTotalVisits").textContent = visits.length;
  document.getElementById("analyticsActivePatients").textContent = patients.length;
  document.getElementById("analyticsLowStock").textContent = inventory.filter(i => i.status === "lowstock").length;

  renderLineChart("visitTrendChart", ["Apr", "May", "Jun", "Jul", "Aug", "Sep"], [0, visits.length, 0, 0, 0, 0]);

  const severityCounts = { mild: 0, moderate: 0, severe: 0 };
  visits.forEach(v => severityCounts[v.severity]++);
  renderDonut("severityDonut", "severityLegend", [
    { label: "mild", value: severityCounts.mild, color: "#2563eb" },
    { label: "moderate", value: severityCounts.moderate, color: "#16a34a" },
    { label: "severe", value: severityCounts.severe, color: "#f59e0b" },
  ]);

  renderBarChart("complaintsBarList", visits.map(v => ({ label: v.reason, count: 1 })));

  const categoryTotals = {};
  inventory.forEach(i => { categoryTotals[i.category] = (categoryTotals[i.category] || 0) + i.qty; });
  const categoryColors = { Medication: "#9333ea", Ppe: "#f59e0b", "First Aid": "#dc2626", Equipment: "#16a34a", Consumable: "#2563eb" };
  const categoryData = Object.entries(categoryTotals)
    .sort((a, b) => b[1] - a[1])
    .map(([label, value]) => ({ label, value, color: categoryColors[label] || "#94a3b8" }));
  renderDonut("categoryDonut", "categoryLegend", categoryData);
}

function updateVisitSummary() {
  const today = new Date();
  today.setMinutes(today.getMinutes() - today.getTimezoneOffset());
  const todayKey = today.toISOString().slice(0, 10);
  document.getElementById("statVisits").textContent = visits.filter(visit => visit.dateKey === todayKey).length;
}

function updateVisitSummary() {
  const today = new Date();
  today.setMinutes(today.getMinutes() - today.getTimezoneOffset());
  const todayKey = today.toISOString().slice(0, 10);
  document.getElementById("statVisits").textContent = visits.filter(visit => visit.dateKey === todayKey).length;
}

document.getElementById("inventorySearchInput").addEventListener("input", filterInventory);
document.getElementById("categoryFilter").addEventListener("change", filterInventory);
const addItemModal = document.getElementById("addItemModal");
const addItemForm = document.getElementById("addItemForm");

function closeAddItemModal() {
  addItemModal.classList.remove("open");
  addItemForm.reset();
}

document.getElementById("addItemBtn").addEventListener("click", () => {
  addItemModal.classList.add("open");
  document.getElementById("itemName").focus();
});
document.getElementById("closeAddItemModal").addEventListener("click", closeAddItemModal);
document.getElementById("cancelAddItem").addEventListener("click", closeAddItemModal);
addItemModal.addEventListener("click", (event) => {
  if (event.target === addItemModal) closeAddItemModal();
});

addItemForm.addEventListener("submit", async (event) => {
  event.preventDefault();
  const payload = {
    type: "inventory",
    name: document.getElementById("itemName").value.trim(),
    category: document.getElementById("itemCategory").value,
    unit: document.getElementById("itemUnit").value,
    qty: Number(document.getElementById("itemQuantity").value),
    min: Number(document.getElementById("itemMinimum").value),
    unitCost: Number(document.getElementById("itemCost").value),
    expiryDate: document.getElementById("itemExpiry").value,
    location: document.getElementById("itemLocation").value.trim(),
    supplier: document.getElementById("itemSupplier").value.trim(),
    description: document.getElementById("itemDescription").value.trim(),
  };

  try {
    await apiRequest(payload);
    await loadClinicData();
    closeAddItemModal();
  } catch (error) {
    alert(`Could not save inventory item: ${error.message}`);
  }
});

const newVisitModal = document.getElementById("newVisitModal");
const newVisitForm = document.getElementById("newVisitForm");
const visitPatientSelect = document.getElementById("visitPatient");

function populateVisitPatients() {
  visitPatientSelect.replaceChildren(new Option("Select patient", ""));
  patients.forEach(patient => {
    visitPatientSelect.add(new Option(`${patient.name} (${patient.id})`, patient.id));
  });
}

function openNewVisitModal() {
  populateVisitPatients();
  newVisitModal.classList.add("open");
  visitPatientSelect.focus();
}

function closeNewVisitModal() {
  newVisitModal.classList.remove("open");
  newVisitForm.reset();
}

document.getElementById("newVisitBtn").addEventListener("click", openNewVisitModal);
document.getElementById("newVisitBtn2").addEventListener("click", openNewVisitModal);
document.getElementById("closeNewVisitModal").addEventListener("click", closeNewVisitModal);
document.getElementById("cancelNewVisit").addEventListener("click", closeNewVisitModal);
newVisitModal.addEventListener("click", (event) => {
  if (event.target === newVisitModal) closeNewVisitModal();
});

newVisitForm.addEventListener("submit", async (event) => {
  event.preventDefault();
  const payload = {
    type: "visit",
    patientId: visitPatientSelect.value,
    reason: document.getElementById("visitComplaint").value.trim(),
    severity: document.getElementById("visitSeverity").value,
    dx: document.getElementById("visitDiagnosis").value.trim() || "Not recorded",
    status: document.getElementById("visitStatus").value,
    attendingStaff: document.getElementById("visitStaff").value.trim(),
    vitals: {
      temperature: document.getElementById("visitTemperature").value,
      bloodPressure: document.getElementById("visitBloodPressure").value.trim(),
      pulse: document.getElementById("visitPulse").value,
      respiratoryRate: document.getElementById("visitRespRate").value,
      weight: document.getElementById("visitWeight").value,
    },
    treatment: document.getElementById("visitTreatment").value.trim(),
    medications: document.getElementById("visitMedication").value.trim(),
    notes: document.getElementById("visitNotes").value.trim(),
  };
  try {
    await apiRequest(payload);
    await loadClinicData();
    closeNewVisitModal();
  } catch (error) {
    alert(`Could not save visit: ${error.message}`);
  }
});

document.getElementById("searchInput").addEventListener("input", (e) => {
  const q = e.target.value.toLowerCase();
  const filtered = patients.filter(p =>
    p.name.toLowerCase().includes(q) ||
    p.id.toLowerCase().includes(q) ||
    p.course.toLowerCase().includes(q)
  );
  renderPatients(filtered);
});

function filterVisitRecords() {
  const q = document.getElementById("visitSearchInput").value.toLowerCase();
  const severity = document.getElementById("severityFilter").value;
  const filtered = visits.filter(v =>
    (severity === "all" || v.severity === severity) &&
    (v.name.toLowerCase().includes(q) || v.reason.toLowerCase().includes(q) || v.dx.toLowerCase().includes(q))
  );
  renderVisitRecords(filtered);
}

document.getElementById("visitSearchInput").addEventListener("input", filterVisitRecords);
document.getElementById("severityFilter").addEventListener("change", filterVisitRecords);

const registerModal = document.getElementById("registerModal");
const registerForm = document.getElementById("registerForm");

function openModal() { registerModal.classList.add("open"); }
function closeModal() {
  registerModal.classList.remove("open");
  registerForm.reset();
}

document.getElementById("registerBtn").addEventListener("click", openModal);
document.getElementById("closeModal").addEventListener("click", closeModal);
document.getElementById("cancelModal").addEventListener("click", closeModal);
registerModal.addEventListener("click", (e) => {
  if (e.target === registerModal) closeModal();
});

registerForm.addEventListener("submit", async (event) => {
  event.preventDefault();
  const payload = {
    type: "patient",
    id: document.getElementById("fId").value.trim(),
    name: document.getElementById("fName").value.trim(),
    course: document.getElementById("fCourse").value.trim(),
    year: document.getElementById("fYear").value,
    blood: document.getElementById("fBlood").value,
  };
  try {
    await apiRequest(payload);
    await loadClinicData();
    closeModal();
  } catch (error) {
    alert(`Could not save patient: ${error.message}`);
  }
});

// switches visible section without a page reload
document.querySelectorAll(".nav-item").forEach(item => {
  item.addEventListener("click", (e) => {
    e.preventDefault();
    const page = item.dataset.page;
    document.querySelectorAll(".nav-item").forEach(i => i.classList.remove("active"));
    item.classList.add("active");
    document.querySelectorAll(".page").forEach(p => { p.hidden = true; });
    const target = document.getElementById(`page-${page}`);
    if (target) target.hidden = false;
  });
});

renderVisits();
renderStock();
renderPatients(patients);
renderVisitRecords(visits);
renderInventory(inventory);
renderAnalytics();
updateVisitSummary();
loadClinicData();