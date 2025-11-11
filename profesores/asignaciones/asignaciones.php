<!doctype html>
<html lang="es">
    <head> 
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Asignaciones</title>
        <script src="/_sdk/data_sdk.js"></script>
        <script src="/_sdk/element_sdk.js"></script>
        <link rel="stylesheet" href="./asignaciones.css">
    </head>

    <body>
        <!-- Fondo animado -->
        <div class="animated-bg"></div>

        <div class="container">
            <div class="header">
                <h1 id="app-title">Sistema de Asignaciones</h1>
                <p id="welcome-message">Gestiona tus asignaciones, establece
                    fechas límite y califica trabajos</p>
            </div>

            <div class="actions">
                <button class="btn btn-primary" id="create-evaluation-btn">➕
                    Crear Nueva Asignación</button>
            </div>

            <div id="evaluations-container"></div>
        </div>

        <!-- Modal Crear Evaluación -->
        <div class="modal" id="create-modal">
            <div class="modal-content">
                <h2>Crear Nueva Evaluación</h2>
                <form id="create-form">
                    <div class="form-group">
                        <label for="eval-title">Título de la Evaluación</label>
                        <input type="text" id="eval-title" required
                            placeholder="Ej: Examen Final de Matemáticas">
                    </div>
                    <div class="form-group">
                        <label for="eval-description">Descripción</label>
                        <textarea id="eval-description" required
                            placeholder="Describe los temas y requisitos de la evaluación"></textarea>
                    </div>
                    <div class="form-group">
                        <label for="eval-deadline">Fecha Límite</label>
                        <input type="datetime-local" id="eval-deadline"
                            required>
                    </div>
                    <div class="form-group">
                        <label for="eval-max-score">Puntuación Máxima</label>
                        <input type="number" id="eval-max-score" required
                            min="1" value="100" placeholder="100">
                    </div>
                    <div class="modal-actions">
                        <button type="button" class="btn btn-secondary"
                            id="cancel-create-btn">Cancelar</button>
                        <button type="submit" class="btn btn-primary"
                            id="submit-create-btn">Crear Evaluación</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Modal Detalles Evaluación -->
        <div class="modal" id="details-modal">
            <div class="modal-content">
                <h2 id="details-title">Detalles de la Evaluación</h2>
                <div id="details-content"></div>
                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary"
                        id="close-details-btn">Cerrar</button>
                </div>
            </div>
        </div>

        <!-- Toast -->
        <div class="toast" id="toast"></div>

        <!-- === SCRIPT ORIGINAL (CRUD, EVALUACIONES, CALIFICACIONES) === -->
        <script>
    const defaultConfig = {
      app_title: "Sistema de Evaluaciones",
      welcome_message: "Gestiona tus evaluaciones, establece fechas límite y califica trabajos",
      primary_color: "#667eea",
      secondary_color: "#ffffff",
      text_color: "#1a202c",
      success_color: "#48bb78",
      danger_color: "#f56565"
    };
    
    let evaluations = [];
    let currentEvaluation = null;
    
    const dataHandler = {
      onDataChanged(data) {
        evaluations = data;
        renderEvaluations();
      }
    };
    
    async function initializeApp() {
      const initResult = await window.dataSdk.init(dataHandler);
      if (!initResult.isOk) {
        showToast('Error al inicializar el sistema', 'error');
        console.error('Failed to initialize data SDK');
      }
    }
    
    function renderEvaluations() {
      const container = document.getElementById('evaluations-container');
      
      if (evaluations.length === 0) {
        container.innerHTML = `
          <div class="empty-state">
            <div class="empty-state-icon">📝</div>
            <h3>No hay evaluaciones creadas</h3>
            <p>Comienza creando tu primera evaluación para gestionar trabajos y calificaciones</p>
          </div>
        `;
        return;
      }
      
      container.innerHTML = `<div class="evaluations-grid">${evaluations.map(eval => {
        const deadline = new Date(eval.deadline);
        const now = new Date();
        const isOverdue = deadline < now;
        const submissions = eval.submissions ? JSON.parse(eval.submissions) : [];
        const gradedCount = submissions.filter(s => s.grade !== null).length;
        
        return `
          <div class="evaluation-card" data-id="${eval.id}">
            <h3>${eval.title}</h3>
            <p>${eval.description}</p>
            <div class="evaluation-meta">
              <div class="evaluation-meta-item">
                <span class="evaluation-meta-label">Fecha límite:</span>
                <span class="evaluation-meta-value ${isOverdue ? 'deadline-warning' : 'deadline-ok'}">
                  ${deadline.toLocaleDateString('es-ES')} ${deadline.toLocaleTimeString('es-ES', {hour: '2-digit', minute: '2-digit'})}
                </span>
              </div>
              <div class="evaluation-meta-item">
                <span class="evaluation-meta-label">Puntuación máxima:</span>
                <span class="evaluation-meta-value">${eval.maxScore} puntos</span>
              </div>
              <div class="evaluation-meta-item">
                <span class="evaluation-meta-label">Trabajos entregados:</span>
                <span class="evaluation-meta-value">${submissions.length}</span>
              </div>
              <div class="evaluation-meta-item">
                <span class="evaluation-meta-label">Calificados:</span>
                <span class="evaluation-meta-value">${gradedCount} de ${submissions.length}</span>
              </div>
            </div>
            <div class="card-actions">
              <button class="btn btn-primary view-btn" data-id="${eval.id}">Ver y Calificar</button>
              <button class="btn btn-danger delete-btn" data-id="${eval.id}">Eliminar</button>
            </div>
          </div>
        `;
      }).join('')}</div>`;
      
      document.querySelectorAll('.view-btn').forEach(btn => {
        btn.addEventListener('click', (e) => {
          const id = e.target.dataset.id;
          showDetails(id);
        });
      });
      
      document.querySelectorAll('.delete-btn').forEach(btn => {
        btn.addEventListener('click', (e) => {
          const id = e.target.dataset.id;
          deleteEvaluation(id);
        });
      });
    }
    
    function showDetails(id) {
      currentEvaluation = evaluations.find(e => e.id === id);
      if (!currentEvaluation) return;
      
      const submissions = currentEvaluation.submissions ? JSON.parse(currentEvaluation.submissions) : [];
      const deadline = new Date(currentEvaluation.deadline);
      
      document.getElementById('details-title').textContent = currentEvaluation.title;
      
      const detailsContent = document.getElementById('details-content');
      detailsContent.innerHTML = `
        <div class="evaluation-meta">
          <div class="evaluation-meta-item">
            <span class="evaluation-meta-label">Descripción:</span>
            <span class="evaluation-meta-value">${currentEvaluation.description}</span>
          </div>
          <div class="evaluation-meta-item">
            <span class="evaluation-meta-label">Fecha límite:</span>
            <span class="evaluation-meta-value">${deadline.toLocaleDateString('es-ES')} ${deadline.toLocaleTimeString('es-ES', {hour: '2-digit', minute: '2-digit'})}</span>
          </div>
          <div class="evaluation-meta-item">
            <span class="evaluation-meta-label">Puntuación máxima:</span>
            <span class="evaluation-meta-value">${currentEvaluation.maxScore} puntos</span>
          </div>
        </div>
        
        <h3 style="margin: 24px 0 16px 0; color: #2d3748;">Trabajos Entregados (${submissions.length})</h3>
        
        ${submissions.length === 0 ? `
          <div style="text-align: center; padding: 32px; background: #f7fafc; border-radius: 8px;">
            <p style="color: #718096; margin: 0;">No hay trabajos entregados aún</p>
          </div>
        ` : `
          <div class="submissions-list">
            ${submissions.map((sub, index) => `
              <div class="submission-item">
                <div class="submission-header">
                  <span class="student-name">👤 ${sub.studentName}</span>
                  <span style="color: #718096; font-size: 14px;">${new Date(sub.submittedAt).toLocaleDateString('es-ES')}</span>
                </div>
                <div class="submission-work">
                  <strong>Trabajo:</strong> ${sub.work}
                </div>
                ${sub.grade !== null ? `
                  <div style="display: flex; justify-content: space-between; align-items: center;">
                    <span class="grade-display">Calificación: ${sub.grade}/${currentEvaluation.maxScore}</span>
                    <button class="btn btn-secondary" onclick="editGrade(${index})">Editar</button>
                  </div>
                ` : `
                  <div class="grade-input-group">
                    <input type="number" id="grade-${index}" min="0" max="${currentEvaluation.maxScore}" placeholder="Calificación (0-${currentEvaluation.maxScore})" />
                    <button class="btn btn-success" onclick="saveGrade(${index})">Calificar</button>
                  </div>
                `}
              </div>
            `).join('')}
          </div>
        `}
        
        <div style="margin-top: 24px; padding: 16px; background: #f7fafc; border-radius: 8px;">
          <h4 style="margin: 0 0 12px 0; color: #2d3748;">Simular Entrega de Trabajo</h4>
          <div class="form-group">
            <label for="student-name">Nombre del Estudiante</label>
            <input type="text" id="student-name" placeholder="Ej: María García" />
          </div>
          <div class="form-group">
            <label for="student-work">Trabajo Entregado</label>
            <textarea id="student-work" placeholder="Describe el trabajo entregado por el estudiante"></textarea>
          </div>
          <button class="btn btn-primary" onclick="submitWork()">Entregar Trabajo</button>
        </div>
      `;
      
      document.getElementById('details-modal').classList.add('active');
    }
    
    async function saveGrade(index) {
      const gradeInput = document.getElementById(`grade-${index}`);
      const grade = parseFloat(gradeInput.value);
      
      if (isNaN(grade) || grade < 0 || grade > currentEvaluation.maxScore) {
        showToast(`La calificación debe estar entre 0 y ${currentEvaluation.maxScore}`, 'error');
        return;
      }
      
      const submissions = JSON.parse(currentEvaluation.submissions);
      submissions[index].grade = grade;
      
      const btn = gradeInput.nextElementSibling;
      btn.disabled = true;
      btn.innerHTML = '<span class="spinner"></span>Guardando...';
      
      const result = await window.dataSdk.update({
        ...currentEvaluation,
        submissions: JSON.stringify(submissions)
      });
      
      btn.disabled = false;
      btn.innerHTML = 'Calificar';
      
      if (result.isOk) {
        showToast('Calificación guardada exitosamente', 'success');
        showDetails(currentEvaluation.id);
      } else {
        showToast('Error al guardar la calificación', 'error');
      }
    }
    
    function editGrade(index) {
      const submissions = JSON.parse(currentEvaluation.submissions);
      submissions[index].grade = null;
      
      currentEvaluation.submissions = JSON.stringify(submissions);
      showDetails(currentEvaluation.id);
    }
    
    async function submitWork() {
      const studentName = document.getElementById('student-name').value.trim();
      const studentWork = document.getElementById('student-work').value.trim();
      
      if (!studentName || !studentWork) {
        showToast('Por favor completa todos los campos', 'error');
        return;
      }
      
      const submissions = currentEvaluation.submissions ? JSON.parse(currentEvaluation.submissions) : [];
      submissions.push({
        studentName,
        work: studentWork,
        submittedAt: new Date().toISOString(),
        grade: null
      });
      
      const result = await window.dataSdk.update({
        ...currentEvaluation,
        submissions: JSON.stringify(submissions)
      });
      
      if (result.isOk) {
        showToast('Trabajo entregado exitosamente', 'success');
        showDetails(currentEvaluation.id);
      } else {
        showToast('Error al entregar el trabajo', 'error');
      }
    }
    
    async function deleteEvaluation(id) {
      const evaluation = evaluations.find(e => e.id === id);
      if (!evaluation) return;
      
      const card = document.querySelector(`[data-id="${id}"]`);
      const deleteBtn = card.querySelector('.delete-btn');
      
      if (deleteBtn.textContent === 'Eliminar') {
        deleteBtn.textContent = '¿Confirmar?';
        deleteBtn.classList.remove('btn-danger');
        deleteBtn.classList.add('btn-secondary');
        
        setTimeout(() => {
          if (deleteBtn.textContent === '¿Confirmar?') {
            deleteBtn.textContent = 'Eliminar';
            deleteBtn.classList.remove('btn-secondary');
            deleteBtn.classList.add('btn-danger');
          }
        }, 3000);
        return;
      }
      
      deleteBtn.disabled = true;
      deleteBtn.innerHTML = '<span class="spinner"></span>Eliminando...';
      
      const result = await window.dataSdk.delete(evaluation);
      
      if (result.isOk) {
        showToast('Evaluación eliminada exitosamente', 'success');
      } else {
        showToast('Error al eliminar la evaluación', 'error');
        deleteBtn.disabled = false;
        deleteBtn.textContent = 'Eliminar';
      }
    }
    
    function showToast(message, type = 'success') {
      const toast = document.getElementById('toast');
      toast.textContent = message;
      toast.className = `toast ${type} show`;
      
      setTimeout(() => {
        toast.classList.remove('show');
      }, 3000);
    }
    
    document.getElementById('create-evaluation-btn').addEventListener('click', () => {
      document.getElementById('create-modal').classList.add('active');
      document.getElementById('create-form').reset();
      
      const now = new Date();
      now.setDate(now.getDate() + 7);
      const dateString = now.toISOString().slice(0, 16);
      document.getElementById('eval-deadline').value = dateString;
    });
    
    document.getElementById('cancel-create-btn').addEventListener('click', () => {
      document.getElementById('create-modal').classList.remove('active');
    });
    
    document.getElementById('close-details-btn').addEventListener('click', () => {
      document.getElementById('details-modal').classList.remove('active');
      currentEvaluation = null;
    });
    
    document.getElementById('create-form').addEventListener('submit', async (e) => {
      e.preventDefault();
      
      if (evaluations.length >= 999) {
        showToast('Límite máximo de 999 evaluaciones alcanzado', 'error');
        return;
      }
      
      const title = document.getElementById('eval-title').value.trim();
      const description = document.getElementById('eval-description').value.trim();
      const deadline = document.getElementById('eval-deadline').value;
      const maxScore = parseInt(document.getElementById('eval-max-score').value);
      
      const submitBtn = document.getElementById('submit-create-btn');
      submitBtn.disabled = true;
      submitBtn.innerHTML = '<span class="spinner"></span>Creando...';
      
      const result = await window.dataSdk.create({
        id: Date.now().toString(),
        title,
        description,
        deadline: new Date(deadline).toISOString(),
        maxScore,
        createdAt: new Date().toISOString(),
        submissions: JSON.stringify([])
      });
      
      submitBtn.disabled = false;
      submitBtn.innerHTML = 'Crear Evaluación';
      
      if (result.isOk) {
        showToast('Evaluación creada exitosamente', 'success');
        document.getElementById('create-modal').classList.remove('active');
      } else {
        showToast('Error al crear la evaluación', 'error');
      }
    });
    
    async function onConfigChange(config) {
      document.getElementById('app-title').textContent = config.app_title || defaultConfig.app_title;
      document.getElementById('welcome-message').textContent = config.welcome_message || defaultConfig.welcome_message;
      
      const primaryColor = config.primary_color || defaultConfig.primary_color;
      const secondaryColor = config.secondary_color || defaultConfig.secondary_color;
      const textColor = config.text_color || defaultConfig.text_color;
      const successColor = config.success_color || defaultConfig.success_color;
      const dangerColor = config.danger_color || defaultConfig.danger_color;
      
      document.body.style.background = `linear-gradient(135deg, ${primaryColor} 0%, #764ba2 100%)`;
      
      document.querySelectorAll('.btn-primary').forEach(btn => {
        btn.style.background = primaryColor;
      });
      
      document.querySelectorAll('.btn-success').forEach(btn => {
        btn.style.background = successColor;
      });
      
      document.querySelectorAll('.btn-danger').forEach(btn => {
        btn.style.background = dangerColor;
      });
      
      document.querySelectorAll('.header, .actions, .evaluation-card, .modal-content, .empty-state').forEach(el => {
        el.style.background = secondaryColor;
      });
      
      document.querySelectorAll('h1, h2, h3, .student-name').forEach(el => {
        el.style.color = textColor;
      });
    }
    
    if (window.elementSdk) {
      window.elementSdk.init({
        defaultConfig,
        onConfigChange,
        mapToCapabilities: (config) => ({
          recolorables: [
            {
              get: () => config.primary_color || defaultConfig.primary_color,
              set: (value) => {
                config.primary_color = value;
                window.elementSdk.setConfig({ primary_color: value });
              }
            },
            {
              get: () => config.secondary_color || defaultConfig.secondary_color,
              set: (value) => {
                config.secondary_color = value;
                window.elementSdk.setConfig({ secondary_color: value });
              }
            },
            {
              get: () => config.text_color || defaultConfig.text_color,
              set: (value) => {
                config.text_color = value;
                window.elementSdk.setConfig({ text_color: value });
              }
            },
            {
              get: () => config.success_color || defaultConfig.success_color,
              set: (value) => {
                config.success_color = value;
                window.elementSdk.setConfig({ success_color: value });
              }
            },
            {
              get: () => config.danger_color || defaultConfig.danger_color,
              set: (value) => {
                config.danger_color = value;
                window.elementSdk.setConfig({ danger_color: value });
              }
            }
          ],
          borderables: [],
          fontEditable: undefined,
          fontSizeable: undefined
        }),
        mapToEditPanelValues: (config) => new Map([
          ["app_title", config.app_title || defaultConfig.app_title],
          ["welcome_message", config.welcome_message || defaultConfig.welcome_message]
        ])
      });
    }
    
    initializeApp();
  </script>
        <script>(function(){function c(){var b=a.contentDocument||a.contentWindow.document;if(b){var d=b.createElement('script');d.innerHTML="window.__CF$cv$params={r:'99adeb2ae37a63b9',t:'MTc2MjUyOTkxNi4wMDAwMDA='};var a=document.createElement('script');a.nonce='';a.src='/cdn-cgi/challenge-platform/scripts/jsd/main.js';document.getElementsByTagName('head')[0].appendChild(a);";b.getElementsByTagName('head')[0].appendChild(d)}}if(document.body){var a=document.createElement('iframe');a.height=1;a.width=1;a.style.position='absolute';a.style.top=0;a.style.left=0;a.style.border='none';a.style.visibility='hidden';document.body.appendChild(a);if('loading'!==document.readyState)c();else if(window.addEventListener)document.addEventListener('DOMContentLoaded',c);else{var e=document.onreadystatechange||function(){};document.onreadystatechange=function(b){e(b);'loading'!==document.readyState&&(document.onreadystatechange=e,c())}}}})();</script>
    </body>
</html>
