<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Certificaciones - Hoot & Learn</title>
    <style>
        body {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f7fafc;
            color: #2d3748;
            min-height: 100%;
        }

        html {
            height: 100%;
        }

        /* === FONDO PROFESIONAL === */
        .animated-bg {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 50%, #cbd5e0 100%);
            z-index: -1;
        }

        .animated-bg::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: 
                radial-gradient(circle at 25% 25%, rgba(102,126,234,0.05) 0%, transparent 50%),
                radial-gradient(circle at 75% 75%, rgba(118,75,162,0.05) 0%, transparent 50%);
            animation: float 20s ease-in-out infinite;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            50% { transform: translateY(-10px) rotate(1deg); }
        }

        /* === HEADER === */
        .header {
            background: rgba(255,255,255,0.2);
            backdrop-filter: blur(20px);
            padding: 1.5rem 2rem;
            border-bottom: 1px solid rgba(255,255,255,0.3);
        }

        .header-content {
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            font-size: 1.5rem;
            font-weight: 800;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .nav-buttons {
            display: flex;
            gap: 1rem;
        }

        .nav-btn {
            background: rgba(255,255,255,0.2);
            color: #2d3748;
            border: 1px solid rgba(255,255,255,0.3);
            padding: 0.5rem 1rem;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
            text-decoration: none;
        }

        .nav-btn:hover {
            background: rgba(255,255,255,0.4);
            transform: translateY(-2px);
        }

        /* === MAIN CONTENT === */
        .main-content {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
        }

        .page-header {
            text-align: center;
            margin-bottom: 3rem;
        }

        .page-title {
            font-size: 2.5rem;
            font-weight: 800;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 1rem;
        }

        .page-subtitle {
            font-size: 1.2rem;
            color: #4a5568;
            max-width: 600px;
            margin: 0 auto;
        }

        /* === CERTIFICACIONES LAYOUT === */
        .certifications-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            margin-bottom: 2rem;
        }

        /* === PANEL DE BÚSQUEDA === */
        .search-panel {
            background: rgba(255,255,255,0.8);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 2rem;
            border: 1px solid rgba(255,255,255,0.9);
            box-shadow: 0 8px 32px rgba(102,126,234,0.1);
            height: fit-content;
        }

        .panel-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: #2d3748;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 0.5rem;
        }

        .form-input, .form-select {
            width: 100%;
            padding: 1rem;
            border: 1px solid rgba(160,174,192,0.3);
            border-radius: 10px;
            background: rgba(255,255,255,0.8);
            color: #2d3748;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .form-input:focus, .form-select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102,126,234,0.1);
            background: rgba(255,255,255,0.95);
        }

        .search-btn {
            width: 100%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 1rem 2rem;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 600;
            font-size: 1.1rem;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .search-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102,126,234,0.3);
        }

        .search-btn:disabled {
            background: rgba(160,174,192,0.5);
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        /* === PANEL DE PREVISUALIZACIÓN === */
        .preview-panel {
            background: rgba(255,255,255,0.8);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 2rem;
            border: 1px solid rgba(255,255,255,0.9);
            box-shadow: 0 8px 32px rgba(102,126,234,0.1);
            display: flex;
            flex-direction: column;
        }

        .preview-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 400px;
        }

        .preview-placeholder {
            text-align: center;
            color: #718096;
        }

        .placeholder-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        .certificate-preview {
            width: 100%;
            max-width: 500px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            margin-bottom: 1.5rem;
            display: none;
        }

        .certificate-preview.show {
            display: block;
        }

        .student-info {
            background: rgba(102,126,234,0.05);
            border: 1px solid rgba(102,126,234,0.2);
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1.5rem;
            display: none;
        }

        .student-info.show {
            display: block;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
        }

        .info-label {
            font-weight: 600;
            color: #4a5568;
        }

        .info-value {
            color: #2d3748;
        }

        .download-actions {
            display: flex;
            gap: 1rem;
            justify-content: center;
        }

        .download-btn {
            background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%);
            color: white;
            border: none;
            padding: 1rem 2rem;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 600;
            font-size: 1rem;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .download-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(34,197,94,0.3);
        }

        .download-btn:disabled {
            background: rgba(160,174,192,0.5);
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .preview-btn {
            background: rgba(255,255,255,0.8);
            color: #4a5568;
            border: 1px solid rgba(160,174,192,0.3);
            padding: 1rem 2rem;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 600;
            font-size: 1rem;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .preview-btn:hover {
            background: rgba(255,255,255,1);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(45,55,72,0.1);
        }



        /* === MODAL DE PREVISUALIZACIÓN === */
        .preview-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.8);
            backdrop-filter: blur(5px);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .preview-modal.active {
            display: flex;
        }

        .modal-content {
            background: rgba(255,255,255,0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 2rem;
            max-width: 90%;
            max-height: 90%;
            overflow: auto;
            border: 1px solid rgba(255,255,255,0.9);
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            position: relative;
        }

        .modal-close {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: rgba(239,68,68,0.1);
            color: #ef4444;
            border: 1px solid rgba(239,68,68,0.2);
            padding: 0.5rem;
            border-radius: 8px;
            cursor: pointer;
            font-size: 1.2rem;
            transition: all 0.3s ease;
        }

        .modal-close:hover {
            background: rgba(239,68,68,0.2);
        }

        .certificate-full {
            width: 100%;
            max-width: 800px;
            border-radius: 15px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.2);
        }

        /* === LOADING STATES === */
        .loading {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            color: #667eea;
        }

        .spinner {
            width: 20px;
            height: 20px;
            border: 2px solid rgba(102,126,234,0.2);
            border-top: 2px solid #667eea;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* === RESPONSIVE === */
        @media (max-width: 1024px) {
            .certifications-container {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .main-content {
                padding: 1rem;
            }
            
            .download-actions {
                flex-direction: column;
            }
            
            .modal-content {
                padding: 1rem;
                margin: 1rem;
            }
        }
    </style>
</head>
<body>
    <!-- === FONDO ANIMADO === -->
    <div class="animated-bg"></div>

    <!-- === HEADER === -->
    <header class="header">
        <div class="header-content">
            <div class="logo">Hoot & Learn</div>
            <div class="nav-buttons">
                <a href="student-dashboard.html" class="nav-btn">Dashboard</a>
                <a href="index.html" class="nav-btn">Cerrar Sesión</a>
            </div>
        </div>
    </header>

    <!-- === MAIN CONTENT === -->
    <main class="main-content">
        <!-- === PAGE HEADER === -->
        <section class="page-header">
            <h1 class="page-title">🎓 Certificaciones</h1>
            <p class="page-subtitle">
                Genera y gestiona certificados de finalización de cursos para estudiantes
            </p>
        </section>

        <!-- === CERTIFICACIONES CONTAINER === -->
        <div class="certifications-container">
            <!-- === PANEL DE BÚSQUEDA === -->
            <div class="search-panel">
                <h2 class="panel-title">
                    <span>🔍</span>
                    Buscar Estudiante
                </h2>

                <form id="searchForm">
                    <div class="form-group">
                        <label class="form-label" for="studentId">ID del Estudiante</label>
                        <input 
                            type="text" 
                            id="studentId" 
                            class="form-input" 
                            placeholder="Ej: EST001, EST002..."
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="courseSelect">Curso/Materia</label>
                        <select id="courseSelect" class="form-select" required>
                            <option value="">Selecciona un curso</option>
                            <option value="js-fundamentals">JavaScript Fundamentals</option>
                            <option value="python-basics">Python para Principiantes</option>
                            <option value="web-design">Diseño Web Moderno</option>
                            <option value="data-science">Introducción a Data Science</option>
                            <option value="react-advanced">React Avanzado</option>
                            <option value="mobile-dev">Desarrollo Mobile</option>
                        </select>
                    </div>

                    <button type="submit" class="search-btn" id="searchBtn">
                        <span>🔍</span>
                        Encontrar Estudiante
                    </button>
                </form>
            </div>

            <!-- === PANEL DE PREVISUALIZACIÓN === -->
            <div class="preview-panel">
                <h2 class="panel-title">
                    <span>👁️</span>
                    Previsualización del Certificado
                </h2>

                <div class="preview-content" id="previewContent">
                    <div class="preview-placeholder" id="placeholder">
                        <div class="placeholder-icon">🎓</div>
                        <h3>Selecciona un estudiante y curso</h3>
                        <p>La previsualización del certificado aparecerá aquí</p>
                    </div>

                    <div class="student-info" id="studentInfo">
                        <!-- Se llenará dinámicamente -->
                    </div>

                    <svg class="certificate-preview" id="certificatePreview" viewBox="0 0 800 600" xmlns="http://www.w3.org/2000/svg">
                        <!-- Fondo del certificado -->
                        <defs>
                            <linearGradient id="bgGradient" x1="0%" y1="0%" x2="100%" y2="100%">
                                <stop offset="0%" style="stop-color:#667eea;stop-opacity:0.1" />
                                <stop offset="100%" style="stop-color:#764ba2;stop-opacity:0.1" />
                            </linearGradient>
                            <linearGradient id="borderGradient" x1="0%" y1="0%" x2="100%" y2="100%">
                                <stop offset="0%" style="stop-color:#667eea" />
                                <stop offset="100%" style="stop-color:#764ba2" />
                            </linearGradient>
                        </defs>
                        
                        <!-- Fondo -->
                        <rect width="800" height="600" fill="white" stroke="url(#borderGradient)" stroke-width="8" rx="20"/>
                        <rect x="20" y="20" width="760" height="560" fill="url(#bgGradient)" rx="15"/>
                        
                        <!-- Decoraciones -->
                        <circle cx="100" cy="100" r="30" fill="none" stroke="url(#borderGradient)" stroke-width="2" opacity="0.3"/>
                        <circle cx="700" cy="100" r="30" fill="none" stroke="url(#borderGradient)" stroke-width="2" opacity="0.3"/>
                        <circle cx="100" cy="500" r="30" fill="none" stroke="url(#borderGradient)" stroke-width="2" opacity="0.3"/>
                        <circle cx="700" cy="500" r="30" fill="none" stroke="url(#borderGradient)" stroke-width="2" opacity="0.3"/>
                        
                        <!-- Logo/Icono -->
                        <circle cx="400" cy="120" r="40" fill="url(#borderGradient)"/>
                        <text x="400" y="135" text-anchor="middle" fill="white" font-size="30" font-weight="bold">🎓</text>
                        
                        <!-- Título -->
                        <text x="400" y="200" text-anchor="middle" fill="#2d3748" font-size="36" font-weight="bold" font-family="serif">
                            CERTIFICADO DE FINALIZACIÓN
                        </text>
                        
                        <!-- Línea decorativa -->
                        <line x1="200" y1="220" x2="600" y2="220" stroke="url(#borderGradient)" stroke-width="2"/>
                        
                        <!-- Texto principal -->
                        <text x="400" y="270" text-anchor="middle" fill="#4a5568" font-size="18" font-family="sans-serif">
                            Se certifica que
                        </text>
                        
                        <!-- Nombre del estudiante -->
                        <text x="400" y="320" text-anchor="middle" fill="#2d3748" font-size="32" font-weight="bold" font-family="serif" id="studentNameText">
                            [Nombre del Estudiante]
                        </text>
                        
                        <!-- Línea bajo el nombre -->
                        <line x1="150" y1="340" x2="650" y2="340" stroke="#cbd5e0" stroke-width="1"/>
                        
                        <!-- Texto del curso -->
                        <text x="400" y="380" text-anchor="middle" fill="#4a5568" font-size="18" font-family="sans-serif">
                            ha completado satisfactoriamente el curso
                        </text>
                        
                        <!-- Nombre del curso -->
                        <text x="400" y="420" text-anchor="middle" fill="#667eea" font-size="24" font-weight="bold" font-family="sans-serif" id="courseNameText">
                            [Nombre del Curso]
                        </text>
                        
                        <!-- Fecha -->
                        <text x="200" y="500" text-anchor="middle" fill="#4a5568" font-size="14" font-family="sans-serif">
                            Fecha de finalización
                        </text>
                        <text x="200" y="520" text-anchor="middle" fill="#2d3748" font-size="16" font-weight="bold" font-family="sans-serif" id="completionDateText">
                            [Fecha]
                        </text>
                        
                        <!-- Firma -->
                        <text x="600" y="500" text-anchor="middle" fill="#4a5568" font-size="14" font-family="sans-serif">
                            Hoot & Learn
                        </text>
                        <text x="600" y="520" text-anchor="middle" fill="#2d3748" font-size="16" font-weight="bold" font-family="sans-serif">
                            Plataforma Educativa
                        </text>
                        
                        <!-- ID del certificado -->
                        <text x="400" y="560" text-anchor="middle" fill="#718096" font-size="12" font-family="monospace" id="certificateIdText">
                            ID: CERT-2024-001
                        </text>
                    </svg>

                    <div class="download-actions" id="downloadActions" style="display: none;">
                        <button class="preview-btn" onclick="openPreviewModal()">
                            <span>👁️</span>
                            Vista Completa
                        </button>
                        <button class="download-btn" onclick="downloadCertificate()">
                            <span>📥</span>
                            Descargar Certificado
                        </button>
                    </div>
                </div>
            </div>
        </div>


    </main>

    <!-- === MODAL DE PREVISUALIZACIÓN === -->
    <div class="preview-modal" id="previewModal">
        <div class="modal-content">
            <button class="modal-close" onclick="closePreviewModal()">×</button>
            <svg class="certificate-full" viewBox="0 0 800 600" xmlns="http://www.w3.org/2000/svg" id="certificateFullPreview">
                <!-- Mismo contenido que el certificado pequeño -->
            </svg>
        </div>
    </div>

    <script>
        // === DATOS DE EJEMPLO ===
        const sampleStudents = {
            'EST001': {
                id: 'EST001',
                name: 'María González Rodríguez',
                email: 'maria.gonzalez@email.com',
                courses: {
                    'js-fundamentals': {
                        completed: true,
                        completionDate: '2024-12-15',
                        grade: 95,
                        courseName: 'JavaScript Fundamentals'
                    },
                    'web-design': {
                        completed: true,
                        completionDate: '2024-11-28',
                        grade: 88,
                        courseName: 'Diseño Web Moderno'
                    }
                }
            },
            'EST002': {
                id: 'EST002',
                name: 'Carlos Mendoza Silva',
                email: 'carlos.mendoza@email.com',
                courses: {
                    'python-basics': {
                        completed: true,
                        completionDate: '2024-12-10',
                        grade: 92,
                        courseName: 'Python para Principiantes'
                    },
                    'data-science': {
                        completed: true,
                        completionDate: '2024-12-05',
                        grade: 89,
                        courseName: 'Introducción a Data Science'
                    }
                }
            },
            'EST003': {
                id: 'EST003',
                name: 'Ana Patricia López',
                email: 'ana.lopez@email.com',
                courses: {
                    'react-advanced': {
                        completed: true,
                        completionDate: '2024-12-12',
                        grade: 96,
                        courseName: 'React Avanzado'
                    }
                }
            }
        };



        let currentStudent = null;
        let currentCourse = null;

        // === INICIALIZACIÓN ===
        document.addEventListener('DOMContentLoaded', function() {
            setupEventListeners();
        });

        // === EVENT LISTENERS ===
        function setupEventListeners() {
            document.getElementById('searchForm').addEventListener('submit', handleSearch);
            
            // Cerrar modal con ESC
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    closePreviewModal();
                }
            });

            // Cerrar modal clickeando fuera
            document.getElementById('previewModal').addEventListener('click', function(e) {
                if (e.target === this) {
                    closePreviewModal();
                }
            });
        }

        // === BÚSQUEDA DE ESTUDIANTE ===
        function handleSearch(e) {
            e.preventDefault();
            
            const studentId = document.getElementById('studentId').value.trim().toUpperCase();
            const courseId = document.getElementById('courseSelect').value;
            
            if (!studentId || !courseId) {
                showError('Por favor completa todos los campos');
                return;
            }

            // Mostrar loading
            showLoading();
            
            // Simular búsqueda en BD
            setTimeout(() => {
                searchStudent(studentId, courseId);
            }, 1000);
        }

        function showLoading() {
            const searchBtn = document.getElementById('searchBtn');
            searchBtn.disabled = true;
            searchBtn.innerHTML = `
                <div class="spinner"></div>
                Buscando...
            `;
        }

        function resetSearchButton() {
            const searchBtn = document.getElementById('searchBtn');
            searchBtn.disabled = false;
            searchBtn.innerHTML = `
                <span>🔍</span>
                Encontrar Estudiante
            `;
        }

        function searchStudent(studentId, courseId) {
            const student = sampleStudents[studentId];
            
            if (!student) {
                resetSearchButton();
                showError('Estudiante no encontrado');
                return;
            }

            const courseData = student.courses[courseId];
            
            if (!courseData) {
                resetSearchButton();
                showError('El estudiante no está inscrito en este curso');
                return;
            }

            if (!courseData.completed) {
                resetSearchButton();
                showError('El estudiante no ha completado este curso');
                return;
            }

            // Estudiante encontrado y curso completado
            currentStudent = student;
            currentCourse = courseData;
            
            resetSearchButton();
            showCertificatePreview();
        }

        // === MOSTRAR PREVISUALIZACIÓN ===
        function showCertificatePreview() {
            // Ocultar placeholder
            document.getElementById('placeholder').style.display = 'none';
            
            // Mostrar información del estudiante
            const studentInfo = document.getElementById('studentInfo');
            studentInfo.innerHTML = `
                <div class="info-row">
                    <span class="info-label">Estudiante:</span>
                    <span class="info-value">${currentStudent.name}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">ID:</span>
                    <span class="info-value">${currentStudent.id}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Curso:</span>
                    <span class="info-value">${currentCourse.courseName}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Calificación:</span>
                    <span class="info-value">${currentCourse.grade}/100</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Fecha de finalización:</span>
                    <span class="info-value">${formatDate(currentCourse.completionDate)}</span>
                </div>
            `;
            studentInfo.classList.add('show');
            
            // Actualizar certificado
            updateCertificateContent();
            
            // Mostrar certificado y acciones
            document.getElementById('certificatePreview').classList.add('show');
            document.getElementById('downloadActions').style.display = 'flex';
        }

        function updateCertificateContent() {
            const certificateId = generateCertificateId();
            
            // Actualizar textos en el SVG
            document.getElementById('studentNameText').textContent = currentStudent.name;
            document.getElementById('courseNameText').textContent = currentCourse.courseName;
            document.getElementById('completionDateText').textContent = formatDate(currentCourse.completionDate);
            document.getElementById('certificateIdText').textContent = `ID: ${certificateId}`;
            
            // También actualizar el modal
            updateModalCertificate(certificateId);
        }

        function updateModalCertificate(certificateId) {
            const modalSvg = document.getElementById('certificateFullPreview');
            modalSvg.innerHTML = document.getElementById('certificatePreview').innerHTML;
        }

        // === MODAL DE PREVISUALIZACIÓN ===
        function openPreviewModal() {
            document.getElementById('previewModal').classList.add('active');
        }

        function closePreviewModal() {
            document.getElementById('previewModal').classList.remove('active');
        }

        // === DESCARGA DE CERTIFICADO ===
        function downloadCertificate() {
            if (!currentStudent || !currentCourse) {
                showError('No hay certificado para descargar');
                return;
            }

            // Crear el certificado como imagen
            const svg = document.getElementById('certificatePreview');
            const svgData = new XMLSerializer().serializeToString(svg);
            const canvas = document.createElement('canvas');
            const ctx = canvas.getContext('2d');
            const img = new Image();

            canvas.width = 800;
            canvas.height = 600;

            img.onload = function() {
                ctx.drawImage(img, 0, 0);
                
                // Crear enlace de descarga
                const link = document.createElement('a');
                link.download = `certificado-${currentStudent.id}-${currentCourse.courseName.replace(/\s+/g, '-').toLowerCase()}.png`;
                link.href = canvas.toDataURL();
                link.click();
                
                // Mostrar confirmación
                showSuccess('Certificado descargado exitosamente');
            };

            img.src = 'data:image/svg+xml;base64,' + btoa(unescape(encodeURIComponent(svgData)));
        }



        // === UTILIDADES ===
        function generateCertificateId() {
            const date = new Date();
            const year = date.getFullYear();
            const month = (date.getMonth() + 1).toString().padStart(2, '0');
            const day = date.getDate().toString().padStart(2, '0');
            const random = Math.floor(Math.random() * 1000).toString().padStart(3, '0');
            
            return `CERT-${year}${month}${day}-${random}`;
        }

        function formatDate(dateString) {
            const date = new Date(dateString);
            const options = { 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric' 
            };
            return date.toLocaleDateString('es-ES', options);
        }

        function getCourseIdFromName(courseName) {
            const courseMap = {
                'JavaScript Fundamentals': 'js-fundamentals',
                'Python para Principiantes': 'python-basics',
                'Diseño Web Moderno': 'web-design',
                'Introducción a Data Science': 'data-science',
                'React Avanzado': 'react-advanced',
                'Desarrollo Mobile': 'mobile-dev'
            };
            return courseMap[courseName] || '';
        }

        function showError(message) {
            // En una implementación real, usarías un sistema de notificaciones más sofisticado
            alert(`❌ Error: ${message}`);
        }

        function showSuccess(message) {
            // En una implementación real, usarías un sistema de notificaciones más sofisticado
            alert(`✅ ${message}`);
        }
    </script>
<script>(function(){function c(){var b=a.contentDocument||a.contentWindow.document;if(b){var d=b.createElement('script');d.innerHTML="window.__CF$cv$params={r:'98c35bafd25ae993',t:'MTc2MDA3MDM3Mi4wMDAwMDA='};var a=document.createElement('script');a.nonce='';a.src='/cdn-cgi/challenge-platform/scripts/jsd/main.js';document.getElementsByTagName('head')[0].appendChild(a);";b.getElementsByTagName('head')[0].appendChild(d)}}if(document.body){var a=document.createElement('iframe');a.height=1;a.width=1;a.style.position='absolute';a.style.top=0;a.style.left=0;a.style.border='none';a.style.visibility='hidden';document.body.appendChild(a);if('loading'!==document.readyState)c();else if(window.addEventListener)document.addEventListener('DOMContentLoaded',c);else{var e=document.onreadystatechange||function(){};document.onreadystatechange=function(b){e(b);'loading'!==document.readyState&&(document.onreadystatechange=e,c())}}}})();</script></body>
</html>
