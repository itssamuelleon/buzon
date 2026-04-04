<?php 
require_once 'config.php';

// 1. Authentication Check
if (!isLoggedIn()) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false) {
        http_response_code(401);
        echo json_encode(['error' => 'No autorizado']);
        exit;
    }
    header('Location: login.php?redirect=' . urlencode('profile.php'));
    exit;
}

// 2. Handle AJAX Requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false) {
    header('Content-Type: application/json');
    $data = json_decode(file_get_contents('php://input'), true);
    $action = $data['action'] ?? '';
    $user_id = $_SESSION['user_id'];

    if ($action === 'validate_current_password') {
        $current_password = $data['current_password'] ?? '';
        
        $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            if (password_verify($current_password, $row['password'])) {
                echo json_encode(['valid' => true]);
            } else {
                echo json_encode(['valid' => false, 'message' => 'La contraseña actual es incorrecta.']);
            }
        } else {
            echo json_encode(['valid' => false, 'message' => 'Usuario no encontrado.']);
        }
        exit;
    }

    if ($action === 'change_password') {
        $new_password = $data['new_password'] ?? '';
        
        if (strlen($new_password) < 8) {
            echo json_encode(['success' => false, 'message' => 'La nueva contraseña debe tener al menos 8 caracteres.']);
            exit;
        }

        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->bind_param("si", $hashed_password, $user_id);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error al actualizar la contraseña.']);
        }
        exit;
    }
    
    echo json_encode(['error' => 'Acción no válida']);
    exit;
}

// 3. Normal Page Rendering
$page_title = 'Mi Perfil - Buzón de Quejas'; 
$show_global_blobs = false; // Disable global blobs for Liquid Glass design
include 'components/header.php'; 
?>

<!-- Liquid Glass Pattern Implementation -->
<div class="fixed inset-0 overflow-hidden pointer-events-none -z-50">
    <div class="absolute inset-0 bg-institutional">
        <div class="absolute inset-0 bg-gradient-to-b from-slate-50/40 via-transparent to-slate-50/40 dark:from-slate-900/60 dark:via-transparent dark:to-slate-900/60"></div>
    </div>
</div>

</style>

<?php

// Data Fetching
$user_id = $_SESSION['user_id'];

// Get user's main information
$stmt = $conn->prepare("SELECT name, email, role, created_at, profile_photo FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Get user's statistics
$stmt_stats = $conn->prepare("SELECT COUNT(id) as total_complaints FROM complaints WHERE user_id = ?");
$stmt_stats->bind_param("i", $user_id);
$stmt_stats->execute();
$stats = $stmt_stats->get_result()->fetch_assoc();

$is_staff = in_array(strtolower($user['role']), ['admin', 'manager']);
$staff_stats = ['comments' => 0, 'attended' => 0];

if ($is_staff) {
    $stmt_staff = $conn->prepare("
        SELECT 
            COUNT(id) as total_comments,
            COUNT(DISTINCT complaint_id) as total_attended
        FROM complaint_comments 
        WHERE user_id = ?
    ");
    $stmt_staff->bind_param("i", $user_id);
    $stmt_staff->execute();
    $s_res = $stmt_staff->get_result()->fetch_assoc();
    $staff_stats['comments'] = $s_res['total_comments'] ?? 0;
    $staff_stats['attended'] = $s_res['total_attended'] ?? 0;
}

function formatRoleName($role) {
    if ($role === 'admin') return 'Administrador';
    if ($role === 'manager') return 'Encargado';
    if ($role === 'student') return 'Estudiante';
    return 'Usuario Estándar';
}
?>

<div class="bg-transparent" x-data="profileManager()">
    <main class="container mx-auto px-4 py-12">
        <div class="max-w-4xl mx-auto">
            
            <div class="liquid-glass rounded-3xl overflow-hidden shadow-2xl">
                <div class="p-8 md:p-12">
                    <!-- Page Header -->
                    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-6 mb-10">
                        <div class="flex items-center gap-4">
                            <?php if (!empty($user['profile_photo'])): ?>
                                <div class="w-20 h-20 rounded-full overflow-hidden flex items-center justify-center flex-shrink-0 shadow-lg border-4 border-white">
                                    <img src="data:image/jpeg;base64,<?php echo $user['profile_photo']; ?>" 
                                         alt="Profile Photo" 
                                         class="w-full h-full object-cover"
                                         onerror="this.onerror=null; this.parentElement.innerHTML='<div class=\'w-20 h-20 bg-gradient-to-br from-blue-500 to-indigo-600 text-white rounded-full flex items-center justify-center\'><span class=\'text-4xl font-bold\'><?php echo strtoupper(substr($user['name'], 0, 1)); ?></span></div>';">
                                </div>
                            <?php else: ?>
                                <div class="w-20 h-20 bg-gradient-to-br from-blue-500 to-indigo-600 text-white rounded-full flex items-center justify-center flex-shrink-0 shadow-lg">
                                    <span class="text-4xl font-bold">
                                        <?php echo strtoupper(substr($user['name'], 0, 1)); ?>
                                    </span>
                                </div>
                            <?php endif; ?>
                            <div>
                                <h1 class="text-3xl md:text-4xl font-bold text-gray-800 dark:text-white">
                                    <?php echo htmlspecialchars($user['name']); ?>
                                </h1>
                                <p class="text-gray-500 dark:text-gray-400 mt-1">Gestiona la información de tu cuenta y revisa tu actividad.</p>
                            </div>
                        </div>
                    </div>

                    <!-- User Statistics -->
                    <div class="grid grid-cols-2 <?php echo $is_staff ? 'md:grid-cols-4' : 'sm:grid-cols-2'; ?> gap-3 mb-8">
                        <div class="flex items-center p-3 glass-inner rounded-lg border border-gray-200/50">
                            <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center flex-shrink-0">
                                <i class="ph-files text-xl text-blue-600"></i>
                            </div>
                            <div class="ml-3 overflow-hidden">
                                <p class="text-gray-500 text-[10px] sm:text-xs uppercase tracking-wide truncate">Enviados</p>
                                <p class="text-base sm:text-lg font-bold text-gray-800 leading-tight"><?php echo $stats['total_complaints']; ?></p>
                            </div>
                        </div>

                        <?php if ($is_staff): ?>
                        <div class="flex items-center p-3 glass-inner rounded-lg border border-gray-200/50">
                            <div class="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center flex-shrink-0">
                                <i class="ph-chat-circle-text text-xl text-purple-600"></i>
                            </div>
                            <div class="ml-3 overflow-hidden">
                                <p class="text-gray-500 text-[10px] sm:text-xs uppercase tracking-wide truncate">Comentarios</p>
                                <p class="text-base sm:text-lg font-bold text-gray-800 leading-tight"><?php echo $staff_stats['comments']; ?></p>
                            </div>
                        </div>

                        <div class="flex items-center p-3 glass-inner rounded-lg border border-gray-200/50">
                            <div class="w-10 h-10 bg-indigo-100 rounded-lg flex items-center justify-center flex-shrink-0">
                                <i class="ph-check-circle text-xl text-indigo-600"></i>
                            </div>
                            <div class="ml-3 overflow-hidden">
                                <p class="text-gray-500 text-[10px] sm:text-xs uppercase tracking-wide truncate">Atendidos</p>
                                <p class="text-base sm:text-lg font-bold text-gray-800 leading-tight"><?php echo $staff_stats['attended']; ?></p>
                            </div>
                        </div>
                        <?php endif; ?>

                        <div class="flex items-center p-3 glass-inner rounded-lg border border-gray-200/50">
                            <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center flex-shrink-0">
                                <i class="ph-calendar-check text-xl text-green-600"></i>
                            </div>
                            <div class="ml-3 overflow-hidden">
                                <p class="text-gray-500 text-[10px] sm:text-xs uppercase tracking-wide truncate">Registro</p>
                                <p class="text-sm font-bold text-gray-800 leading-tight truncate"><?php echo date('d M Y', strtotime($user['created_at'])); ?></p>
                            </div>
                        </div>
                    </div>

                    <!-- Profile Details -->
                    <div class="space-y-6">
                        <h3 class="text-xl font-bold text-gray-800 dark:text-white border-b border-gray-200/50 pb-3">Información de la Cuenta</h3>
                        
                        <div class="flex items-center p-4">
                            <div class="w-10 h-10 flex items-center justify-center text-gray-400 mr-4"><i class="ph-user text-2xl"></i></div>
                            <div class="flex-grow">
                                <p class="text-sm text-gray-500">Nombre Completo</p>
                                <p class="text-base font-semibold text-gray-700"><?php echo htmlspecialchars($user['name']); ?></p>
                            </div>
                        </div>
                        
                        <div class="flex items-center p-4 glass-inner rounded-lg">
                            <div class="w-10 h-10 flex items-center justify-center text-gray-400 mr-4"><i class="ph-envelope-simple text-2xl"></i></div>
                            <div class="flex-grow">
                                <p class="text-sm text-gray-500">Dirección de Correo</p>
                                <p class="text-base font-semibold text-gray-700 dark:text-gray-300"><?php echo htmlspecialchars($user['email']); ?></p>
                            </div>
                        </div>

                        <div class="flex items-center p-4">
                            <div class="w-10 h-10 flex items-center justify-center text-gray-400 mr-4"><i class="ph-shield-check text-2xl"></i></div>
                            <div class="flex-grow">
                                <p class="text-sm text-gray-500">Rol de Usuario</p>
                                <p class="text-base font-semibold text-gray-700"><?php echo formatRoleName($user['role']); ?></p>
                            </div>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <?php if (isAdmin()): ?>
                    <div class="mt-12 pt-8 border-t border-gray-200 flex flex-col sm:flex-row gap-4">
                        <button type="button" 
                                @click="openPasswordModal()"
                                class="w-full sm:w-auto flex justify-center items-center bg-gray-200 text-gray-800 font-semibold py-3 px-6 rounded-lg hover:bg-gray-300 transition-colors duration-300">
                            <i class="ph-key text-lg mr-2"></i>
                            Cambiar Contraseña
                        </button>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <!-- Change Password Modal -->
    <div x-show="showPasswordModal" 
         x-transition:enter="ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         @keydown.escape.window="closePasswordModal()"
         x-cloak
         class="fixed inset-0 z-[9999] flex items-center justify-center p-4"
         style="display: none;"
         x-init="$watch('showPasswordModal', value => { document.body.style.overflow = value ? 'hidden' : 'auto' })">
        
        <!-- Backdrop -->
        <div class="fixed inset-0 bg-black/50 backdrop-blur-sm" @click="closePasswordModal()"></div>

        <!-- Modal Content -->
        <div @click.away="closePasswordModal()"
             x-transition:enter="ease-out duration-300"
             x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100"
             x-transition:leave="ease-in duration-200"
             x-transition:leave-start="opacity-100 scale-100"
             x-transition:leave-end="opacity-0 scale-95"
             class="relative liquid-glass rounded-3xl shadow-2xl max-w-md w-full p-8 z-10 border border-white/20">
            
            <div class="text-center mb-6">
                <div class="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="ph-lock-key text-3xl text-blue-600"></i>
                </div>
                <h3 class="text-2xl font-bold text-gray-800 mb-2">Cambiar Contraseña</h3>
                
                <!-- Step 1 Title -->
                <p x-show="step === 1" class="text-gray-600">Por seguridad, ingresa tu contraseña actual</p>
                
                <!-- Step 2 Title -->
                <p x-show="step === 2" class="text-gray-600">Ingresa tu nueva contraseña</p>
                
                <!-- Step 3 Title -->
                <p x-show="step === 3" class="text-gray-600">¡Tu contraseña ha sido actualizada!</p>
            </div>

            <div class="space-y-4">
                
                <!-- Step 1: Current Password -->
                <div x-show="step === 1" class="space-y-4">
                    <div>
                        <label for="current_password" class="block text-sm font-medium text-gray-700 mb-2">Contraseña Actual</label>
                        <input type="password" 
                               x-model="currentPassword" 
                               id="current_password" 
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors" 
                               placeholder="••••••••">
                        <p x-show="errorMessage" class="mt-2 text-sm text-red-600 flex items-center gap-1">
                            <i class="ph-warning-circle"></i> <span x-text="errorMessage"></span>
                        </p>
                    </div>
                    
                    <div class="flex gap-3 mt-6">
                        <button type="button" 
                                @click="closePasswordModal()"
                                class="flex-1 px-4 py-3 bg-gray-200 text-gray-700 font-semibold rounded-lg hover:bg-gray-300 transition-colors">
                            Cancelar
                        </button>
                        <button type="button" 
                                @click="validateCurrentPassword()"
                                :disabled="isLoading || !currentPassword"
                                class="flex-1 px-4 py-3 bg-blue-600 text-white font-semibold rounded-lg hover:bg-blue-700 transition-colors shadow-lg hover:shadow-xl disabled:opacity-50 disabled:cursor-not-allowed flex justify-center items-center">
                            <span x-show="!isLoading">Siguiente</span>
                            <span x-show="isLoading" class="flex items-center">
                                <i class="ph-spinner animate-spin mr-2"></i> Verificando...
                            </span>
                        </button>
                    </div>
                </div>

                <!-- Step 2: New Password -->
                <div x-show="step === 2" class="space-y-4">
                    <div>
                        <label for="new_password" class="block text-sm font-medium text-gray-700 mb-2">Nueva Contraseña</label>
                        <input type="password" 
                               x-model="newPassword" 
                               id="new_password" 
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors" 
                               placeholder="Mínimo 8 caracteres">
                    </div>
                    <div>
                        <label for="confirm_password" class="block text-sm font-medium text-gray-700 mb-2">Confirmar Nueva Contraseña</label>
                        <input type="password" 
                               x-model="confirmPassword" 
                               id="confirm_password" 
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors" 
                               placeholder="Repite la contraseña">
                        <p x-show="errorMessage" class="mt-2 text-sm text-red-600 flex items-center gap-1">
                            <i class="ph-warning-circle"></i> <span x-text="errorMessage"></span>
                        </p>
                    </div>

                    <div class="flex gap-3 mt-6">
                        <button type="button" 
                                @click="closePasswordModal()"
                                class="flex-1 px-4 py-3 bg-gray-200 text-gray-700 font-semibold rounded-lg hover:bg-gray-300 transition-colors">
                            Cancelar
                        </button>
                        <button type="button" 
                                @click="changePassword()"
                                :disabled="isLoading || !newPassword || !confirmPassword"
                                class="flex-1 px-4 py-3 bg-blue-600 text-white font-semibold rounded-lg hover:bg-blue-700 transition-colors shadow-lg hover:shadow-xl disabled:opacity-50 disabled:cursor-not-allowed flex justify-center items-center">
                            <span x-show="!isLoading">Confirmar</span>
                            <span x-show="isLoading" class="flex items-center">
                                <i class="ph-spinner animate-spin mr-2"></i> Guardando...
                            </span>
                        </button>
                    </div>
                </div>
                
                <!-- Step 3: Success -->
                <div x-show="step === 3" class="text-center py-2">
                    <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-green-100 mb-6 animate-bounce-slow">
                        <i class="ph-check text-green-600 text-3xl"></i>
                    </div>
                    <p class="text-gray-500 mb-8">Ahora puedes usar tu nueva contraseña para iniciar sesión.</p>
                    
                    <button type="button" 
                            @click="closePasswordModal()"
                            class="w-full px-4 py-3 bg-blue-600 text-white font-semibold rounded-lg hover:bg-blue-700 transition-colors shadow-lg hover:shadow-xl">
                        Cerrar
                    </button>
                </div>

            </div>
        </div>
    </div>
</div>

<script>
function profileManager() {
    return {
        showPasswordModal: false,
        step: 1,
        currentPassword: '',
        newPassword: '',
        confirmPassword: '',
        errorMessage: '',
        isLoading: false,

        openPasswordModal() {
            this.showPasswordModal = true;
            this.resetForm();
        },

        closePasswordModal() {
            this.showPasswordModal = false;
            setTimeout(() => this.resetForm(), 300);
        },

        resetForm() {
            this.step = 1;
            this.currentPassword = '';
            this.newPassword = '';
            this.confirmPassword = '';
            this.errorMessage = '';
            this.isLoading = false;
        },

        async validateCurrentPassword() {
            if (!this.currentPassword) return;
            
            this.isLoading = true;
            this.errorMessage = '';

            try {
                const response = await fetch('profile.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        action: 'validate_current_password',
                        current_password: this.currentPassword
                    })
                });

                const data = await response.json();

                if (data.valid) {
                    this.step = 2;
                } else {
                    this.errorMessage = data.message || 'Contraseña incorrecta';
                }
            } catch (error) {
                this.errorMessage = 'Ocurrió un error. Intenta de nuevo.';
                console.error(error);
            } finally {
                this.isLoading = false;
            }
        },

        async changePassword() {
            if (this.newPassword !== this.confirmPassword) {
                this.errorMessage = 'Las contraseñas no coinciden';
                return;
            }

            if (this.newPassword.length < 8) {
                this.errorMessage = 'La contraseña debe tener al menos 8 caracteres';
                return;
            }

            this.isLoading = true;
            this.errorMessage = '';

            try {
                const response = await fetch('profile.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        action: 'change_password',
                        new_password: this.newPassword
                    })
                });

                const data = await response.json();

                if (data.success) {
                    this.step = 3;
                } else {
                    this.errorMessage = data.message || 'Error al cambiar la contraseña';
                }
            } catch (error) {
                this.errorMessage = 'Ocurrió un error. Intenta de nuevo.';
                console.error(error);
            } finally {
                this.isLoading = false;
            }
        }
    }
}
</script>

<?php include 'components/footer.php'; ?>