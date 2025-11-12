<!DOCTYPE html>
<html lang="es">
<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro de Profesor - Hoot & Learn</title>
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
            background: linear-gradient(135deg, #1a202c 0%, #2d3748 50%, #4a5568 100%);
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
                radial-gradient(circle at 20% 30%, rgba(34,197,94,0.1) 0%, transparent 50%),
                radial-gradient(circle at 80% 70%, rgba(102,126,234,0.1) 0%, transparent 50%),
                radial-gradient(circle at 40% 80%, rgba(118,75,162,0.05) 0%, transparent 50%);
            animation: float 25s ease-in-out infinite;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            33% { transform: translateY(-15px) rotate(1deg); }
            66% { transform: translateY(-5px) rotate(-1deg); }
        }

        /* === CONTAINER PRINCIPAL === */
        .register-container {
            min-height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }

        .register-card {
            background: rgba(255,255,255,0.95);
            backdrop-filter: blur(20px);
            border-radius: 24px;
            padding: 3rem;
            width: 100%;
            max-width: 700px;
            border: 1px solid rgba(255,255,255,0.9);
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            position: relative;
            overflow: hidden;
        }

        .register-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #22c55e 0%, #16a34a 50%, #667eea 100%);
        }

        /* === HEADER === */
        .register-header {
            text-align: center;
            margin-bottom: 2.5rem;
        }

        .logo {
            font-size: 2rem;
            font-weight: 800;
            background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 0.5rem;
        }

        .register-title {
            font-size: 1.8rem;
            font-weight: 700;
            color: #2d3748;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .register-subtitle {
            color: #4a5568;
            font-size: 1rem;
            font-weight: 500;
            line-height: 1.5;
        }

        /* === FORMULARIO === */
        .register-form {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .form-label {
            font-weight: 600;
            color: #2d3748;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .form-input {
            padding: 1rem 1.25rem;
            border: 2px solid rgba(160,174,192,0.3);
            border-radius: 12px;
            background: rgba(255,255,255,0.8);
            color: #2d3748;
            font-size: 1rem;
            transition: all 0.3s ease;
            font-weight: 500;
        }

        .form-input:focus {
            outline: none;
            border-color: #22c55e;
            box-shadow: 0 0 0 4px rgba(34,197,94,0.1);
            background: rgba(255,255,255,0.95);
            transform: translateY(-1px);
        }

        .form-input::placeholder {
            color: #a0aec0;
        }

        .form-input.error {
            border-color: #ef4444;
            box-shadow: 0 0 0 4px rgba(239,68,68,0.1);
        }

        .form-hint {
            color: #718096;
            font-size: 0.8rem;
            margin-top: 0.25rem;
            display: block;
        }

        .error-message {
            color: #ef4444;
            font-size: 0.8rem;
            font-weight: 500;
            margin-top: 0.25rem;
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }

        /* === CHECKBOX === */
        .checkbox-group {
            display: flex;
            flex-direction: column;
            gap: 1rem;
            margin: 1rem 0;
        }

        .checkbox-label {
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
            cursor: pointer;
            font-size: 0.9rem;
            color: #4a5568;
            line-height: 1.4;
        }

        .checkbox-label input[type="checkbox"] {
            margin: 0;
            width: 18px;
            height: 18px;
            accent-color: #22c55e;
            flex-shrink: 0;
            margin-top: 0.1rem;
        }

        .checkbox-label a {
            color: #22c55e;
            text-decoration: none;
            font-weight: 600;
        }

        .checkbox-label a:hover {
            text-decoration: underline;
        }

        /* === BOTÓN DE REGISTRO === */
        .register-btn {
            background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%);
            color: white;
            border: none;
            padding: 1.25rem 2rem;
            border-radius: 12px;
            cursor: pointer;
            font-weight: 700;
            font-size: 1.1rem;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            margin-top: 1rem;
            position: relative;
            overflow: hidden;
        }

        .register-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }

        .register-btn:hover::before {
            left: 100%;
        }

        .register-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(34,197,94,0.4);
        }

        .register-btn:disabled {
            background: rgba(160,174,192,0.5);
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        /* === LOADING STATE === */
        .loading {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .spinner {
            width: 20px;
            height: 20px;
            border: 2px solid rgba(255,255,255,0.3);
            border-top: 2px solid white;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* === DIVIDER === */
        .divider {
            display: flex;
            align-items: center;
            margin: 2rem 0 1.5rem 0;
            color: #718096;
            font-size: 0.9rem;
        }

        .divider::before,
        .divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: rgba(160,174,192,0.3);
        }

        .divider span {
            padding: 0 1rem;
            background: rgba(255,255,255,0.95);
        }

        /* === ENLACES ADICIONALES === */
        .additional-links {
            text-align: center;
            margin-top: 1.5rem;
        }

        .link-text {
            color: #4a5568;
            font-size: 0.9rem;
            margin-bottom: 1rem;
        }

        .action-link {
            color: #22c55e;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.9rem;
            padding: 0.75rem 1.5rem;
            border: 1px solid rgba(34,197,94,0.2);
            border-radius: 8px;
            transition: all 0.3s ease;
            background: rgba(34,197,94,0.05);
            display: inline-block;
        }

        .action-link:hover {
            background: rgba(34,197,94,0.1);
            border-color: rgba(34,197,94,0.4);
            transform: translateY(-1px);
        }

        /* === FOOTER === */
        .register-footer {
            text-align: center;
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 1px solid rgba(160,174,192,0.2);
        }

        .back-links {
            display: flex;
            justify-content: center;
            gap: 2rem;
            flex-wrap: wrap;
        }

        .back-link {
            color: #4a5568;
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
            padding: 0.5rem 1rem;
            border-radius: 8px;
        }

        .back-link:hover {
            color: #2d3748;
            background: rgba(74,85,104,0.05);
        }

        /* === RESPONSIVE === */
        @media (max-width: 768px) {
            .register-container {
                padding: 1rem;
            }
            
            .register-card {
                padding: 2rem;
            }
            
            .register-title {
                font-size: 1.5rem;
            }

            .form-row {
                grid-template-columns: 1fr;
            }

            .back-links {
                flex-direction: column;
                align-items: center;
                gap: 1rem;
            }
        }

        /* === ANIMACIONES === */
        .register-card {
            animation: slideUp 0.6s ease-out;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* === SUCCESS MESSAGE === */
        .success-message {
            background: rgba(34,197,94,0.1);
            border: 1px solid rgba(34,197,94,0.3);
            color: #16a34a;
            padding: 1rem;
            border-radius: 12px;
            margin: 1rem 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <!-- === FONDO ANIMADO === -->
    <div class="animated-bg"></div>

    <!-- === CONTAINER PRINCIPAL === -->
    <div class="register-container">
        <div class="register-card">
            <!-- === HEADER === -->
            <div class="register-header">
                <div class="logo">Hoot & Learn</div>
                <h1 class="register-title">
                    <span>✨</span>
                    Registro de Profesor
                </h1>
                <p class="register-subtitle">
                    Únete a nuestra comunidad educativa y transforma la experiencia de aprendizaje de tus estudiantes
                </p>
            </div>

            <!-- === FORMULARIO === -->
            <form class="register-form" id="registerForm">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="firstName">
                            <span>👤</span>
                            Nombre(s)
                        </label>
                        <input 
                            type="text"
                            id="firstName"
                            name="MstroNombre"
                            class="form-input"
                            placeholder="María Fernanda"
                            required
                        >
                        <div class="error-message" id="firstNameError" style="display: none;"></div>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="lastName">
                            <span>🆔</span>
                            Apellidos
                        </label>
                        <input 
                            type="text"
                            id="lastName"
                            name="MstroAps"
                            class="form-input"
                            placeholder="García López"
                            required
                        >
                        <div class="error-message" id="lastNameError" style="display: none;"></div>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="email">
                        <span>📧</span>
                        Correo Electrónico Institucional
                    </label>
                    <input 
                        type="email" 
                        id="email" 
                        class="form-input" 
                        placeholder="profesor@universidad.edu"
                        required
                    >
                    <small class="form-hint">Debe ser un correo institucional válido (.edu, .ac., universidad, etc.)</small>
                    <div class="error-message" id="emailError" style="display: none;"></div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="department">
                        <span>🏢</span>
                        Departamento/Facultad
                    </label>
                    <select id="department" name="MstroDpto" class="form-input" required>
                        <option value="">Selecciona tu departamento</option>
                        <option value="Ciencias de la Computación">Ciencias de la Computación</option>
                        <option value="Matemáticas">Matemáticas</option>
                        <option value="Ingeniería">Ingeniería</option>
                        <option value="Física">Física</option>
                        <option value="Química">Química</option>
                        <option value="Biología">Biología</option>
                        <option value="Administración de Empresas">Administración de Empresas</option>
                        <option value="Psicología">Psicología</option>
                        <option value="Ciencias de la Educación">Ciencias de la Educación</option>
                        <option value="Idiomas y Literatura">Idiomas y Literatura</option>
                        <option value="Artes y Humanidades">Artes y Humanidades</option>
                        <option value="Medicina">Medicina</option>
                        <option value="Derecho">Derecho</option>
                    </select>
                    <div class="error-message" id="departmentError" style="display: none;"></div>
                </div>


                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="password">
                            <span>🔒</span>
                            Contraseña
                        </label>
                        <input 
                            type="password"
                            id="password"
                            name="MstroPassword"
                            class="form-input"
                            placeholder="Mínimo 8 caracteres"
                            required
                        >
                        <div class="error-message" id="passwordError" style="display: none;"></div>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="confirmPassword">
                            <span>✅</span>
                            Confirmar Contraseña
                        </label>
                        <input 
                            type="password"
                            id="confirmPassword"
                            class="form-input"
                            placeholder="Repite tu contraseña"
                            required
                        >
                        <div class="error-message" id="confirmPasswordError" style="display: none;"></div>
                    </div>
                </div>

                <div class="checkbox-group">
                    <label class="checkbox-label" for="agreeTerms">
                        <input type="checkbox" id="agreeTerms" required>
                        Acepto los <a href="#" onclick="showTerms(); return false;">términos y condiciones</a>.
                    </label>

                    <label class="checkbox-label" for="agreeInstitutional">
                        <input type="checkbox" id="agreeInstitutional" required>
                        Confirmo que soy empleado de una institución educativa.
                    </label>

                    <label class="checkbox-label" for="agreeUpdates">
                        <input type="checkbox" id="agreeUpdates">
                        Deseo recibir novedades y actualizaciones.
                    </label>
                </div>

                <button type="submit" class="register-btn" id="registerBtn">
                    <span>🚀</span>
                    Crear Cuenta de Profesor
                </button>
            </form>

            <!-- === DIVIDER === -->
            <div class="divider">
                <span>¿Ya tienes cuenta?</span>
            </div>

            <!-- === ENLACES ADICIONALES === -->
            <div class="additional-links">
                <a href="teacher-login.html" class="action-link">
                    🔑 Iniciar Sesión
                </a>
            </div>

            <!-- === FOOTER === -->
            <div class="register-footer">
                <div class="back-links">
                    <a href="profesor-portal.php" class="back-link">
                        ← Volver al portal
                    </a>
                    <a href="index.html" class="back-link">
                        🏠 Inicio
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Quitar almacenamiento local y usar backend PHP

        document.addEventListener('DOMContentLoaded', function() {
            setupEventListeners();
            showWelcomeAnimation();
        });

        function setupEventListeners() {
            document.getElementById('registerForm').addEventListener('submit', handleRegister);

            document.getElementById('firstName').addEventListener('blur', () => validateField('firstName'));
            document.getElementById('lastName').addEventListener('blur', () => validateField('lastName'));
            document.getElementById('email').addEventListener('blur', validateEmail);
            document.getElementById('department').addEventListener('change', () => validateField('department'));
            document.getElementById('password').addEventListener('blur', validatePassword);
            document.getElementById('confirmPassword').addEventListener('blur', validateConfirmPassword);

            const inputs = ['firstName', 'lastName', 'email', 'department', 'password', 'confirmPassword', 'employeeId'];
            inputs.forEach(inputId => {
                const el = document.getElementById(inputId);
                if (el) el.addEventListener('input', () => clearFieldError(inputId));
            });
        }

        function showWelcomeAnimation() {
            const card = document.querySelector('.register-card');
            card.style.transform = 'translateY(30px)';
            card.style.opacity = '0';
            setTimeout(() => {
                card.style.transition = 'all 0.6s ease-out';
                card.style.transform = 'translateY(0)';
                card.style.opacity = '1';
            }, 100);
        }

        function validateField(fieldId) {
            const field = document.getElementById(fieldId);
            const value = (field?.value || '').trim();

            if (!value) {
                showFieldError(fieldId, 'Este campo es obligatorio');
                return false;
            }
            clearFieldError(fieldId);
            return true;
        }

        function validateEmail() {
            const email = document.getElementById('email');
            const value = email.value.trim().toLowerCase();

            if (!value) {
                showFieldError('email', 'El correo electrónico es obligatorio');
                return false;
            }
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(value)) {
                showFieldError('email', 'Por favor ingresa un correo válido');
                return false;
            }
            // const institutionalDomains = ['.edu', '.ac.', 'universidad', 'instituto', 'college', 'school'];
            // const hasInstitutionalDomain = institutionalDomains.some(domain => value.includes(domain));
            // if (!hasInstitutionalDomain) {
            //     showFieldError('email', 'Debe ser un correo institucional válido (.edu, .ac., universidad, etc.)');
            //     return false;
            // }
            clearFieldError('email');
            return true;
        }

        function validatePassword() {
            const password = document.getElementById('password');
            const value = password.value;

            /*if (!value) {
                showFieldError('password', 'La contraseña es obligatoria');
                return false;
            }
            if (value.length < 8) {
                showFieldError('password', 'La contraseña debe tener al menos 8 caracteres');
                return false;
            }
            const hasLetter = /[a-zA-Z]/.test(value);
            const hasNumber = /\d/.test(value);
            if (!hasLetter || !hasNumber) {
                showFieldError('password', 'La contraseña debe contener al menos una letra y un número');
                return false;
            }
            clearFieldError('password');*/
            return true;
        }

        function validateConfirmPassword() {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirmPassword');
            const value = confirmPassword.value;

            if (!value) {
                showFieldError('confirmPassword', 'Confirma tu contraseña');
                return false;
            }
            if (value !== password) {
                showFieldError('confirmPassword', 'Las contraseñas no coinciden');
                return false;
            }
            clearFieldError('confirmPassword');
            return true;
        }

        function showFieldError(fieldId, message) {
            const field = document.getElementById(fieldId);
            const errorDiv = document.getElementById(fieldId + 'Error');
            if (field) field.classList.add('error');
            if (errorDiv) {
                errorDiv.innerHTML = `<span>⚠️</span><span>${message}</span>`;
                errorDiv.style.display = 'flex';
            }
        }

        function clearFieldError(fieldId) {
            const field = document.getElementById(fieldId);
            const errorDiv = document.getElementById(fieldId + 'Error');
            if (field) field.classList.remove('error');
            if (errorDiv) errorDiv.style.display = 'none';
        }

        async function handleRegister(e) {
            e.preventDefault();

            const formData = {
                firstName: document.getElementById('firstName').value.trim(),
                lastName: document.getElementById('lastName').value.trim(),
                email: document.getElementById('email').value.trim().toLowerCase(),
                department: document.getElementById('department').value,
                employeeId: document.getElementById('employeeId').value.trim(),
                password: document.getElementById('password').value,
                confirmPassword: document.getElementById('confirmPassword').value,
                agreeTerms: document.getElementById('agreeTerms').checked,
                agreeInstitutional: document.getElementById('agreeInstitutional').checked,
                agreeUpdates: document.getElementById('agreeUpdates').checked
            };

            let isValid = true;
            if (!validateField('firstName')) isValid = false;
            if (!validateField('lastName')) isValid = false;
            if (!validateEmail()) isValid = false;
            if (!validateField('department')) isValid = false;
            if (!validatePassword()) isValid = false;
            if (!validateConfirmPassword()) isValid = false;
            if (!formData.agreeTerms) { showError('Debes aceptar los términos y condiciones'); isValid = false; }
            if (!formData.agreeInstitutional) { showError('Debes confirmar que eres empleado de una institución educativa'); isValid = false; }

            if (!isValid) return;

            showLoading();
            try {
                const res = await fetch('guardar_profesor.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        MstroNombre: formData.firstName,
                        MstroAps: formData.lastName,
                        MstroCorreo: formData.email,
                        MstroDpto: formData.department,
                        MstroPassword: formData.password
                    })
                });

                const data = await res.json();
                if (!res.ok || !data.success) {
                    resetRegisterButton();
                    showError(data.message || 'Error al registrar. Intenta nuevamente.');
                    return;
                }

                showSuccess({
                    id: `PROF${String(data.insertId).padStart(3, '0')}`,
                    firstName: formData.firstName,
                    lastName: formData.lastName
                });
            } catch (err) {
                resetRegisterButton();
                console.error(err);
                showError('No se pudo conectar con el servidor.');
            }
        }

        function showLoading() {
            const registerBtn = document.getElementById('registerBtn');
            registerBtn.disabled = true;
            registerBtn.innerHTML = `
                <div class="loading">
                    <div class="spinner"></div>
                    <span>Creando tu cuenta...</span>
                </div>
            `;
        }

        function resetRegisterButton() {
            const registerBtn = document.getElementById('registerBtn');
            registerBtn.disabled = false;
            registerBtn.innerHTML = `
                <span>🚀</span>
                Crear Cuenta de Profesor
            `;
        }

        function showSuccess(professorData) {
            resetRegisterButton();
            const successDiv = document.createElement('div');
            successDiv.className = 'success-message';
            successDiv.innerHTML = `
                <span>✅</span>
                <div>
                    <strong>¡Cuenta creada exitosamente!</strong><br>
                    Tu ID de profesor es: <strong>${professorData.id}</strong><br>
                    Serás redirigido al login en unos segundos...
                </div>
            `;
            const form = document.getElementById('registerForm');
            form.parentNode.insertBefore(successDiv, form.nextSibling);

            const inputs = form.querySelectorAll('input, select, button');
            inputs.forEach(input => input.disabled = true);

            setTimeout(() => {
                window.location.href = 'profesor-login.php';
            }, 3000);
        }

        function showTerms() {
            showInfo('📋 TÉRMINOS Y CONDICIONES PARA PROFESORES...');
        }

        function showPrivacy() {
            showInfo('🔒 POLÍTICA DE PRIVACIDAD...');
        }

        function showError(message) {
            alert('❌ ' + message);
        }

        function showInfo(message) {
            alert('ℹ️ ' + message);
        }

        console.log('🎯 Registro de Profesores usando backend PHP');
    </script>
<script>(function(){function c(){var b=a.contentDocument||a.contentWindow.document;if(b){var d=b.createElement('script');d.innerHTML="window.__CF$cv$params={r:'98e4365bd3ca69e2',t:'MTc2MDQxNDg3My4wMDAwMDA='};var a=document.createElement('script');a.nonce='';a.src='/cdn-cgi/challenge-platform/scripts/jsd/main.js';document.getElementsByTagName('head')[0].appendChild(a);";b.getElementsByTagName('head')[0].appendChild(d)}}if(document.body){var a=document.createElement('iframe');a.height=1;a.width=1;a.style.position='absolute';a.style.top=0;a.style.left=0;a.style.border='none';a.style.visibility='hidden';document.body.appendChild(a);if('loading'!==document.readyState)c();else if(window.addEventListener)document.addEventListener('DOMContentLoaded',c);else{var e=document.onreadystatechange||function(){};document.onreadystatechange=function(b){e(b);'loading'!==document.readyState&&(document.onreadystatechange=e,c())}}}})();</script></body>
</html>
