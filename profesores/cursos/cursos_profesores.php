<?php
session_start();

// Buscar conexion.php en rutas comunes y requerir la primera encontrada
$possible = [
    __DIR__ . '/conexion.php',         // misma carpeta
    __DIR__ . '/../conexion.php',      // un nivel arriba (probable)
    __DIR__ . '/../portal/conexion.php', // carpeta portal
    __DIR__ . '/../../conexion.php'    // dos niveles arriba
];

$found = false;
foreach ($possible as $p) {
    if (file_exists($p)) {
        require_once $p;
        $found = true;
        break;
    }
}
if (! $found) {
    http_response_code(500);
    die("Error: no se encontró conexion.php. Rutas comprobadas: " . implode(', ', $possible));
}

// Mostrar cursos creados por el docente (maestro)
// Se espera que la sesión del profesor tenga 'teacher_id' o 'IDMstro'
$teacherId = isset($_SESSION['teacher_id']) ? (int)$_SESSION['teacher_id'] : (isset($_SESSION['IDMstro']) ? (int)$_SESSION['IDMstro'] : 0);
$teacherName = isset($_SESSION['teacher_name']) ? $_SESSION['teacher_name'] : 'Profesor';
$courses = [];

if ($teacherId > 0) {
    $stmt = $conn->prepare("
        SELECT id, codigo, titulo, descripcion, creado_en
        FROM cursos
        WHERE maestro_id = ?
        ORDER BY creado_en DESC
    ");
    if ($stmt) {
        $stmt->bind_param("i", $teacherId);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $courseId = (int)$row['id'];

            // Actividades del curso (si existe la tabla actividades)
            $activities = [];
            $aStmt = $conn->prepare("SELECT DATE_FORMAT(fecha, '%Y-%m-%d') AS date, titulo, tipo FROM actividades WHERE curso_id = ? ORDER BY fecha");
            if ($aStmt) {
                $aStmt->bind_param("i", $courseId);
                $aStmt->execute();
                $aRes = $aStmt->get_result();
                while ($ar = $aRes->fetch_assoc()) {
                    $activities[] = [
                        'date'  => $ar['date'],
                        'title' => $ar['titulo'],
                        'type'  => $ar['tipo']
                    ];
                }
                $aStmt->close();
            }

            $courses[] = [
                'id' => $courseId,
                'codigo' => $row['codigo'],
                'title' => $row['titulo'],
                'descripcion' => $row['descripcion'],
                'creado_en' => $row['creado_en'],
                'activities' => $activities,
                // valores por defecto usados por la UI previa
                'icon' => '📚',
                'progress' => 0,
                'lessons' => 0,
                'completed' => 0
            ];
        }
        $stmt->close();
    }
}

$sampleCoursesJson = json_encode($courses, JSON_UNESCAPED_UNICODE);

// Cerrar conexión
$conn->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Mis Cursos - Hoot & Learn</title>
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

        /* === FONDO ANIMADO === */
        .animated-bg {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, #e2e8f0 0%, #cbd5e0 25%, #a0aec0 50%, #718096 75%, #4a5568 100%);
            background-size: 400% 400%;
            animation: gradientShift 15s ease infinite;
            z-index: -1;
        }

        @keyframes gradientShift {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        /* === HEADER === */
        .header {
            background: rgba(255,255,255,0.2);
            backdrop-filter: blur(20px);
            padding: 1.5rem 2rem;
            border-bottom: 1px solid rgba(255,255,255,0.3);
        }

        .header-content {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            font-size: 1.5rem;
            font-weight: 800;
            background: linear-gradient(135deg, #2d3748 0%, #4a5568 100%);
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
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }

        /* === BIENVENIDA === */
        .welcome-section {
            background: rgba(255,255,255,0.8);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 2rem;
            margin-bottom: 3rem;
            text-align: center;
            border: 1px solid rgba(255,255,255,0.9);
            box-shadow: 0 8px 32px rgba(45,55,72,0.1);
        }

        .welcome-title {
            font-size: 2rem;
            font-weight: 800;
            margin-bottom: 0.5rem;
            background: linear-gradient(135deg, #2d3748 0%, #5a67d8 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .welcome-subtitle {
            color: #4a5568;
            font-size: 1.1rem;
        }

        /* === LAYOUT PRINCIPAL === */
        .main-layout {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2rem;
            margin-bottom: 2rem;
        }

        /* === CURSOS === */
        .courses-section {
            background: rgba(255,255,255,0.8);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 2rem;
            border: 1px solid rgba(255,255,255,0.9);
            box-shadow: 0 8px 32px rgba(45,55,72,0.1);
        }

        .section-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: #2d3748;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .courses-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
        }

        .course-card {
            background: rgba(255,255,255,0.6);
            border-radius: 15px;
            padding: 1.5rem;
            border: 1px solid rgba(255,255,255,0.8);
            transition: all 0.3s ease;
            cursor: pointer;
            position: relative;
            overflow: hidden;
        }

        .course-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #5a67d8, #667eea);
            transform: scaleX(0);
            transition: transform 0.3s ease;
        }

        .course-card:hover::before {
            transform: scaleX(1);
        }

        .course-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(45,55,72,0.15);
            background: rgba(255,255,255,0.8);
        }

        .course-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .course-icon {
            font-size: 2rem;
        }

        .course-info {
            flex: 1;
        }

        .course-title {
            font-weight: 700;
            color: #2d3748;
            margin-bottom: 0.2rem;
        }

        .course-instructor {
            font-size: 0.9rem;
            color: #4a5568;
        }

        .course-progress {
            margin-bottom: 1rem;
        }

        .progress-label {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
            color: #4a5568;
        }

        .progress-bar {
            width: 100%;
            height: 8px;
            background: rgba(160,174,192,0.3);
            border-radius: 4px;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #5a67d8, #667eea);
            border-radius: 4px;
            transition: width 0.3s ease;
        }

        .course-stats {
            display: flex;
            justify-content: space-between;
            font-size: 0.9rem;
            color: #4a5568;
        }

        /* === CALENDARIO === */
        .calendar-section {
            background: rgba(255,255,255,0.8);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 2rem;
            border: 1px solid rgba(255,255,255,0.9);
            box-shadow: 0 8px 32px rgba(45,55,72,0.1);
        }

        .calendar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .calendar-nav {
            display: flex;
            gap: 0.5rem;
        }

        .calendar-nav-btn {
            background: rgba(90,103,216,0.1);
            color: #5a67d8;
            border: 1px solid rgba(90,103,216,0.2);
            padding: 0.5rem;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .calendar-nav-btn:hover {
            background: rgba(90,103,216,0.2);
        }

        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 1px;
            background: rgba(160,174,192,0.2);
            border-radius: 8px;
            overflow: hidden;
            margin-bottom: 1rem;
        }

        .calendar-day {
            background: rgba(255,255,255,0.8);
            padding: 0.5rem;
            text-align: center;
            font-size: 0.9rem;
            min-height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
        }

        .calendar-day.header {
            background: rgba(90,103,216,0.1);
            font-weight: 600;
            color: #5a67d8;
        }

        .calendar-day.has-activity {
            background: rgba(90,103,216,0.1);
            color: #5a67d8;
            font-weight: 600;
        }

        .calendar-day.has-activity::after {
            content: '';
            position: absolute;
            bottom: 2px;
            left: 50%;
            transform: translateX(-50%);
            width: 6px;
            height: 6px;
            background: #5a67d8;
            border-radius: 50%;
        }

        .activities-list {
            max-height: 200px;
            overflow-y: auto;
        }

        .activity-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem;
            background: rgba(255,255,255,0.5);
            border-radius: 8px;
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
        }

        .activity-date {
            font-weight: 600;
            color: #5a67d8;
            min-width: 60px;
        }

        .activity-title {
            color: #2d3748;
        }

        /* === MODAL CURSO === */
        .course-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            backdrop-filter: blur(5px);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .course-modal.active {
            display: flex;
        }

        .modal-content {
            background: rgba(255,255,255,0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 2rem;
            max-width: 800px;
            width: 90%;
            max-height: 80%;
            overflow-y: auto;
            border: 1px solid rgba(255,255,255,0.9);
            box-shadow: 0 20px 60px rgba(45,55,72,0.3);
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }

        .modal-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: #2d3748;
        }

        .close-btn {
            background: rgba(239,68,68,0.1);
            color: #ef4444;
            border: 1px solid rgba(239,68,68,0.2);
            padding: 0.5rem;
            border-radius: 8px;
            cursor: pointer;
            font-size: 1.2rem;
            transition: all 0.3s ease;
        }

        .close-btn:hover {
            background: rgba(239,68,68,0.2);
        }

        .modal-actions {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .action-btn {
            flex: 1;
            background: linear-gradient(135deg, #5a67d8 0%, #667eea 100%);
            color: white;
            border: none;
            padding: 1rem;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(90,103,216,0.3);
        }

        .action-btn.secondary {
            background: linear-gradient(135deg, #4a5568 0%, #718096 100%);
        }

        /* === RESPONSIVE === */
        @media (max-width: 768px) {
            .main-layout {
                grid-template-columns: 1fr;
            }
            
            .courses-grid {
                grid-template-columns: 1fr;
            }
            
            .calendar-grid {
                font-size: 0.8rem;
            }
            
            .modal-content {
                width: 95%;
                padding: 1.5rem;
            }
            
            .modal-actions {
                flex-direction: column;
            }
        }
    </style>
    <script>
        // === DATOS DINÁMICOS DESDE PHP ===
        const sampleCourses = <?php echo $sampleCoursesJson ?: '[]'; ?>;
        let currentDate = new Date();
        let selectedCourse = null;
        
        // === INICIALIZACIÓN ===
        document.addEventListener('DOMContentLoaded', function() {
            loadUserData();
            loadCourses();
            loadCalendar();
            loadActivities();
        });

        // === CARGAR DATOS DEL USUARIO ===
        function loadUserData() {
            const studentName = localStorage.getItem('studentName') || 'Estudiante';
            document.getElementById('welcomeMessage').textContent = `Bienvenido de vuelta, ${studentName}`;
        }

        // === CARGAR CURSOS ===
        function loadCourses() {
            const coursesGrid = document.getElementById('coursesGrid');
            
            if (sampleCourses.length === 0) {
                coursesGrid.innerHTML = `
                    <div style="grid-column: 1/-1; text-align: center; padding: 3rem; color: #4a5568;">
                        <div style="font-size: 3rem; margin-bottom: 1rem;">📚</div>
                        <h3>No tienes cursos creados</h3>
                        <p>¡Crea tu primer curso para comenzar a enseñar!</p>
                        <button id="addCourseBtn" class="nav-btn" style="display: inline-block; margin-top: 1rem; padding: 1rem 2rem; border-radius: 10px; font-weight: 600;">
                            ➕ Agregar Curso
                        </button>
                    </div>
                `;
                const addBtn = coursesGrid.querySelector('#addCourseBtn');
                if (addBtn) addBtn.addEventListener('click', openAddCourseModal);
                return;
            }

            coursesGrid.innerHTML = sampleCourses.map(course => `
                <div class="course-card" onclick="openCourseModal(${course.id})">
                    <div class="course-header">
                        <div class="course-icon">${course.icon}</div>
                        <div class="course-info">
                            <div class="course-title">${course.title}</div>
                            <div class="course-instructor">${course.instructor}</div>
                        </div>
                    </div>
                    <div class="course-progress">
                        <div class="progress-label">
                            <span>Progreso</span>
                            <span>${course.progress}%</span>
                        </div>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: ${course.progress}%"></div>
                        </div>
                    </div>
                    <div class="course-stats">
                        <span>${course.completed}/${course.lessons} lecciones</span>
                        <span>${course.activities.length} pendientes</span>
                    </div>
                </div>
            `).join('');
        }

        // === CARGAR CALENDARIO ===
        function loadCalendar() {
            const calendarGrid = document.getElementById('calendarGrid');
            const currentMonth = document.getElementById('currentMonth');
            
            const monthNames = [
                'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
                'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'
            ];
            
            const dayNames = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];
            
            currentMonth.textContent = `${monthNames[currentDate.getMonth()]} ${currentDate.getFullYear()}`;
            
            // Obtener todas las actividades
            const allActivities = sampleCourses.flatMap(course => 
                course.activities.map(activity => ({
                    ...activity,
                    courseTitle: course.title
                }))
            );
            
            // Crear calendario
            let calendarHTML = '';
            
            // Headers de días
            dayNames.forEach(day => {
                calendarHTML += `<div class="calendar-day header">${day}</div>`;
            });
            
            // Días del mes
            const firstDay = new Date(currentDate.getFullYear(), currentDate.getMonth(), 1);
            const lastDay = new Date(currentDate.getFullYear(), currentDate.getMonth() + 1, 0);
            const startDate = new Date(firstDay);
            startDate.setDate(startDate.getDate() - firstDay.getDay());
            
            for (let i = 0; i < 42; i++) {
                const date = new Date(startDate);
                date.setDate(startDate.getDate() + i);
                
                const dateStr = date.toISOString().split('T')[0];
                const hasActivity = allActivities.some(activity => activity.date === dateStr);
                const isCurrentMonth = date.getMonth() === currentDate.getMonth();
                
                calendarHTML += `
                    <div class="calendar-day ${hasActivity ? 'has-activity' : ''}" 
                         style="${!isCurrentMonth ? 'opacity: 0.3;' : ''}">
                        ${date.getDate()}
                    </div>
                `;
            }
            
            calendarGrid.innerHTML = calendarHTML;
        }

        // === CARGAR ACTIVIDADES ===
        function loadActivities() {
            const activitiesList = document.getElementById('activitiesList');
            
            const allActivities = sampleCourses.flatMap(course => 
                course.activities.map(activity => ({
                    ...activity,
                    courseTitle: course.title
                }))
            ).sort((a, b) => new Date(a.date) - new Date(b.date));
            
            if (allActivities.length === 0) {
                activitiesList.innerHTML = `
                    <div style="text-align: center; padding: 1rem; color: #4a5568;">
                        📝 No hay actividades pendientes
                    </div>
                `;
                return;
            }
            
            activitiesList.innerHTML = allActivities.map(activity => {
                const date = new Date(activity.date);
                const dateStr = `${date.getDate()}/${date.getMonth() + 1}`;
                const typeIcon = activity.type === 'exam' ? '📊' : activity.type === 'quiz' ? '❓' : '📝';
                
                return `
                    <div class="activity-item">
                        <div class="activity-date">${dateStr}</div>
                        <div class="activity-title">${typeIcon} ${activity.title} — ${activity.courseTitle}</div>
                    </div>
                `;
            }).join('');
        }

        // === NAVEGACIÓN CALENDARIO ===
        function previousMonth() {
            currentDate.setMonth(currentDate.getMonth() - 1);
            loadCalendar();
        }

        function nextMonth() {
            currentDate.setMonth(currentDate.getMonth() + 1);
            loadCalendar();
        }

        // === MODAL CURSO ===
        function openCourseModal(courseId) {
            selectedCourse = sampleCourses.find(course => course.id === courseId);
            if (!selectedCourse) return;
            
            const modal = document.getElementById('courseModal');
            const modalTitle = document.getElementById('modalCourseTitle');
            
            modalTitle.textContent = selectedCourse.title;
            loadModalCalendar();
            loadModalActivities();
            
            modal.classList.add('active');
        }

        function closeCourseModal() {
            const modal = document.getElementById('courseModal');
            modal.classList.remove('active');
            selectedCourse = null;
        }

        function loadModalCalendar() {
            if (!selectedCourse) return;
            
            const modalCalendarGrid = document.getElementById('modalCalendarGrid');
            const dayNames = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];
            
            let calendarHTML = '';
            
            // Headers de días
            dayNames.forEach(day => {
                calendarHTML += `<div class="calendar-day header">${day}</div>`;
            });
            
            // Días del mes
            const firstDay = new Date(currentDate.getFullYear(), currentDate.getMonth(), 1);
            const startDate = new Date(firstDay);
            startDate.setDate(startDate.getDate() - firstDay.getDay());
            
            for (let i = 0; i < 42; i++) {
                const date = new Date(startDate);
                date.setDate(startDate.getDate() + i);
                
                const dateStr = date.toISOString().split('T')[0];
                const hasActivity = selectedCourse.activities.some(activity => activity.date === dateStr);
                const isCurrentMonth = date.getMonth() === currentDate.getMonth();
                
                calendarHTML += `
                    <div class="calendar-day ${hasActivity ? 'has-activity' : ''}" 
                         style="${!isCurrentMonth ? 'opacity: 0.3;' : ''}">
                        ${date.getDate()}
                    </div>
                `;
            }
            
            modalCalendarGrid.innerHTML = calendarHTML;
        }

        function loadModalActivities() {
            if (!selectedCourse) return;
            
            const modalActivitiesList = document.getElementById('modalActivitiesList');
            
            if (selectedCourse.activities.length === 0) {
                modalActivitiesList.innerHTML = `
                    <div style="text-align: center; padding: 1rem; color: #4a5568;">
                        📝 No hay actividades pendientes en este curso
                    </div>
                `;
                return;
            }
            
            modalActivitiesList.innerHTML = selectedCourse.activities.map(activity => {
                const date = new Date(activity.date);
                const dateStr = `${date.getDate()}/${date.getMonth() + 1}`;
                const typeIcon = activity.type === 'exam' ? '📊' : activity.type === 'quiz' ? '❓' : '📝';
                
                return `
                    <div class="activity-item">
                        <div class="activity-date">${dateStr}</div>
                        <div class="activity-title">${typeIcon} ${activity.title}</div>
                    </div>
                `;
            }).join('');
        }

        // === ACCIONES DEL MODAL ===
        function showActivities() {
            if (!selectedCourse) return;
            window.location.href = `course-activities.php?courseId=${selectedCourse.id}&courseName=${encodeURIComponent(selectedCourse.title)}`;
        }

        function showEvaluations() {
            if (!selectedCourse) return;
            window.location.href = `course-evaluations.php?courseId=${selectedCourse.id}&courseName=${encodeURIComponent(selectedCourse.title)}`;
        }

        // === AGREGAR CURSO (MODAL) ===
        function openAddCourseModal() {
            const modal = document.getElementById('addCourseModal');
            if (!modal) return;
            modal.classList.add('active');
            const form = modal.querySelector('form');
            if (form) form.reset();
        }

        function closeAddCourseModal() {
            const modal = document.getElementById('addCourseModal');
            if (!modal) return;
            modal.classList.remove('active');
        }

        async function submitAddCourseForm(evt) {
            evt.preventDefault();
            const form = evt.target;
            const submitBtn = form.querySelector('button[type="submit"]');
            const formData = new FormData(form);

            submitBtn.disabled = true;
            submitBtn.textContent = 'Creando...';

            try {
                const resp = await fetch('create_course.php', {
                    method: 'POST',
                    body: formData
                });
                if (!resp.ok) throw new Error('Error al crear el curso');
                const json = await resp.json();
                if (json.success) {
                    // cerrar modal y recargar para ver el curso creado
                    closeAddCourseModal();
                    location.reload();
                } else {
                    alert(json.message || 'No se pudo crear el curso');
                }
            } catch (err) {
                alert('Error de red o servidor: ' + err.message);
            } finally {
                submitBtn.disabled = false;
                submitBtn.textContent = 'Crear Curso';
            }
        }
    </script>
</head>
<body>
    <!-- === FONDO ANIMADO === -->
    <div class="animated-bg"></div>

    <!-- === HEADER === -->
    <header class="header">
        <div class="header-content">
            <div class="logo">Hoot & Learn</div>
            <div class="nav-buttons">
                <a href="student-dashboard.php" class="nav-btn">← Dashboard</a>
                <a href="logout.php" class="nav-btn">Cerrar Sesión</a>
            </div>
        </div>
    </header>

    <!-- === MAIN CONTENT === -->
    <main class="main-content">
        <!-- === BIENVENIDA === -->
        <section class="welcome-section">
            <h1 class="welcome-title">Gestionar Mis Cursos 📚</h1>
            <p class="welcome-subtitle" id="welcomeMessage">Bienvenido de vuelta, continúa con tu aprendizaje</p>
        </section>

        <!-- === LAYOUT PRINCIPAL === -->
        <div class="main-layout">
            <!-- === CURSOS === -->
            <section class="courses-section">
                <h2 class="section-title">
                    <span>📖</span>
                    Cursos Inscritos
                </h2>
                <div class="courses-grid" id="coursesGrid">
                    <!-- Se llenará dinámicamente -->
                </div>
            </section>

            <!-- === CALENDARIO === -->
            <section class="calendar-section">
                <div class="calendar-header" style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem">
                    <h2 class="section-title">
                        <span>📅</span>
                        <span id="currentMonth"></span>
                    </h2>
                    <div class="calendar-nav">
                        <button class="nav-btn" onclick="previousMonth()">‹</button>
                        <button class="nav-btn" onclick="nextMonth()">›</button>
                    </div>
                </div>
                <div class="calendar-grid" id="calendarGrid">
                    <!-- Se llenará dinámicamente -->
                </div>
                <div class="activities-list" id="activitiesList">
                    <!-- Se llenará dinámicamente -->
                </div>
            </section>
        </div>
    </main>

    <!-- === MODAL CURSO === -->
    <div class="course-modal" id="courseModal">
        <div class="modal-content">
            <div class="modal-header" style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem">
                <h3 class="modal-title" id="modalCourseTitle">Curso Seleccionado</h3>
                <button class="nav-btn" onclick="closeCourseModal()">×</button>
            </div>
            
            <div class="modal-actions" style="display:flex;gap:1rem;margin-bottom:1rem">
                <button class="nav-btn" onclick="showActivities()">
                    📝 Actividades
                </button>
                <button class="nav-btn" onclick="showEvaluations()">
                    📊 Evaluaciones
                </button>
            </div>

            <div class="calendar-section" style="padding:0">
                <h4 class="section-title" style="margin-top:0">
                    <span>📅</span>
                    Calendario del Curso
                </h4>
                <div class="calendar-grid" id="modalCalendarGrid">
                    <!-- Se llenará dinámicamente -->
                </div>
                <div class="activities-list" id="modalActivitiesList">
                    <!-- Se llenará dinámicamente -->
                </div>
            </div>
        </div>
    </div>

    <!-- === MODAL AGREGAR CURSO === -->
    <div class="course-modal" id="addCourseModal" aria-hidden="true">
        <div class="modal-content" role="dialog" aria-modal="true" aria-labelledby="addCourseTitle">
            <div class="modal-header" style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem">
                <h3 class="modal-title" id="addCourseTitle">Crear Nuevo Curso</h3>
                <button class="nav-btn" onclick="closeAddCourseModal()">×</button>
            </div>

            <form onsubmit="submitAddCourseForm(event)" style="display:flex;flex-direction:column;gap:0.75rem">
                <label>
                    Código
                    <input name="codigo" type="text" required style="width:100%;padding:0.6rem;border-radius:8px;border:1px solid #e2e8f0" />
                </label>

                <label>
                    Título
                    <input name="titulo" type="text" required style="width:100%;padding:0.6rem;border-radius:8px;border:1px solid #e2e8f0" />
                </label>

                <label>
                    Descripción
                    <textarea name="descripcion" rows="4" style="width:100%;padding:0.6rem;border-radius:8px;border:1px solid #e2e8f0"></textarea>
                </label>

                <div style="display:flex;gap:0.75rem;margin-top:0.5rem">
                    <button type="submit" class="action-btn">Crear Curso</button>
                    <button type="button" class="nav-btn" onclick="closeAddCourseModal()">Cancelar</button>
                </div>
            </form>
        </div>
    </div>

</body>
</html>
