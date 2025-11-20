<?php 
// config.php should be included via header.php, which also starts the session.
$page_title = 'Mi Perfil - ITSCC Buzón'; 
include 'components/header.php'; 

// 1. Authentication Check: Redirect if not logged in
if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

// 2. Data Fetching
$user_id = $_SESSION['user_id'];

// Get user's main information
$stmt = $conn->prepare("SELECT name, email, role, created_at FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Get user's statistics (e.g., total complaints)
$stmt_stats = $conn->prepare("SELECT COUNT(id) as total_complaints FROM complaints WHERE user_id = ?");
$stmt_stats->bind_param("i", $user_id);
$stmt_stats->execute();
$stats = $stmt_stats->get_result()->fetch_assoc();

// Helper function to format the role name
function formatRoleName($role) {
    if ($role === 'admin') return 'Administrador';
    if ($role === 'manager') return 'Encargado';
    if ($role === 'student') return 'Estudiante';
    return 'Usuario Estándar';
}
?>

<div class="bg-gray-50 min-h-screen">
    <main class="container mx-auto px-4 py-12">
        <div class="max-w-4xl mx-auto">
            
            <div class="bg-white rounded-2xl shadow-xl overflow-hidden">
                <div class="p-8 md:p-12">
                    <!-- Page Header -->
                    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-6 mb-10">
                        <div class="flex items-center gap-4">
                            <div class="w-20 h-20 bg-gradient-to-br from-blue-500 to-indigo-600 text-white rounded-full flex items-center justify-center flex-shrink-0 shadow-lg">
                                <span class="text-4xl font-bold">
                                    <?php echo strtoupper(substr($user['name'], 0, 1)); ?>
                                </span>
                            </div>
                            <div>
                                <h1 class="text-3xl md:text-4xl font-bold text-gray-800">
                                    <?php echo htmlspecialchars($user['name']); ?>
                                </h1>
                                <p class="text-gray-500 mt-1">Gestiona la información de tu cuenta y revisa tu actividad.</p>
                            </div>
                        </div>
                    </div>

                    <!-- User Statistics -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-10">
                        <div class="flex items-center p-4 bg-gray-50 rounded-lg border border-gray-200">
                            <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center flex-shrink-0">
                                <i class="ph-files text-2xl text-blue-600"></i>
                            </div>
                            <div class="ml-4">
                                <p class="text-gray-500 text-sm">Reportes Enviados</p>
                                <p class="text-xl font-bold text-gray-800"><?php echo $stats['total_complaints']; ?></p>
                            </div>
                        </div>
                        <div class="flex items-center p-4 bg-gray-50 rounded-lg border border-gray-200">
                            <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center flex-shrink-0">
                                <i class="ph-calendar-check text-2xl text-green-600"></i>
                            </div>
                            <div class="ml-4">
                                <p class="text-gray-500 text-sm">Miembro Desde</p>
                                <p class="text-xl font-bold text-gray-800"><?php echo date('d M, Y', strtotime($user['created_at'])); ?></p>
                            </div>
                        </div>
                    </div>

                    <!-- Profile Details -->
                    <div class="space-y-6">
                        <h3 class="text-xl font-bold text-gray-800 border-b pb-3">Información de la Cuenta</h3>
                        
                        <div class="flex items-center p-4">
                            <div class="w-10 h-10 flex items-center justify-center text-gray-400 mr-4"><i class="ph-user text-2xl"></i></div>
                            <div class="flex-grow">
                                <p class="text-sm text-gray-500">Nombre Completo</p>
                                <p class="text-base font-semibold text-gray-700"><?php echo htmlspecialchars($user['name']); ?></p>
                            </div>
                        </div>
                        
                        <div class="flex items-center p-4 bg-gray-50 rounded-lg">
                            <div class="w-10 h-10 flex items-center justify-center text-gray-400 mr-4"><i class="ph-envelope-simple text-2xl"></i></div>
                            <div class="flex-grow">
                                <p class="text-sm text-gray-500">Dirección de Correo</p>
                                <p class="text-base font-semibold text-gray-700"><?php echo htmlspecialchars($user['email']); ?></p>
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
                    <div class="mt-12 pt-8 border-t border-gray-200 flex flex-col sm:flex-row gap-4">
                        <a href="#" class="w-full sm:w-auto flex justify-center items-center bg-blue-600 text-white font-semibold py-3 px-6 rounded-lg hover:bg-blue-700 transition-colors duration-300 shadow">
                            <i class="ph-pencil-simple text-lg mr-2"></i>
                            Editar Perfil
                        </a>
                        <a href="#" class="w-full sm:w-auto flex justify-center items-center bg-gray-200 text-gray-800 font-semibold py-3 px-6 rounded-lg hover:bg-gray-300 transition-colors duration-300">
                            <i class="ph-key text-lg mr-2"></i>
                            Cambiar Contraseña
                        </a>
                    </div>
                </div>
            </div>
             <p class="text-center mt-8 text-sm text-gray-500">Para cualquier cambio que no puedas realizar aquí, por favor contacta al administrador.</p>
        </div>
    </main>
</div>

<?php include 'components/footer.php'; ?>