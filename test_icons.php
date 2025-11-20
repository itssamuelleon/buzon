<?php 
$page_title = 'Test de Iconos - ITSCC'; 
include 'components/header.php'; 
?>

<section class="py-20 bg-white">
    <div class="container mx-auto px-4">
        <h1 class="text-4xl font-bold text-slate-900 mb-12 text-center">Test de Iconos - Categorías</h1>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
            <?php
            $category_info = [
                0  => ['from' => 'from-gray-500',    'to' => 'to-slate-500',   'icon' => 'ph-file-text', 'name' => 'Sin Categoría'],
                1  => ['from' => 'from-blue-500',    'to' => 'to-cyan-500',    'icon' => 'ph-wifi-high', 'name' => 'Conectividad'],
                2  => ['from' => 'from-indigo-500',  'to' => 'to-purple-500',  'icon' => 'ph-armchair', 'name' => 'Mobiliario'],
                3  => ['from' => 'from-emerald-500', 'to' => 'to-teal-500',    'icon' => 'ph-books', 'name' => 'Académico'],
                4  => ['from' => 'from-amber-500',   'to' => 'to-orange-500',  'icon' => 'ph-flask', 'name' => 'Laboratorios'],
                5  => ['from' => 'from-green-500',   'to' => 'to-emerald-600', 'icon' => 'ph-basketball', 'name' => 'Deportes'],
                6  => ['from' => 'from-amber-500',   'to' => 'to-orange-600',  'icon' => 'ph-fork-knife', 'name' => 'Cafetería'],
                7  => ['from' => 'from-sky-500',     'to' => 'to-blue-500',    'icon' => 'ph-toilet', 'name' => 'Sanitarios'],
                8  => ['from' => 'from-zinc-500',    'to' => 'to-slate-600',   'icon' => 'ph-car', 'name' => 'Transporte'],
                9  => ['from' => 'from-fuchsia-500', 'to' => 'to-purple-600',  'icon' => 'ph-chalkboard-teacher', 'name' => 'Enseñanza'],
                10 => ['from' => 'from-indigo-500',  'to' => 'to-blue-600',    'icon' => 'ph-book-open', 'name' => 'Biblioteca'],
                11 => ['from' => 'from-yellow-500',  'to' => 'to-amber-600',   'icon' => 'ph-exam', 'name' => 'Exámenes'],
                12 => ['from' => 'from-blue-500',    'to' => 'to-indigo-600',  'icon' => 'ph-folders', 'name' => 'Documentación'],
                13 => ['from' => 'from-emerald-500', 'to' => 'to-teal-600',    'icon' => 'ph-handshake', 'name' => 'Relaciones'],
                14 => ['from' => 'from-rose-500',    'to' => 'to-pink-600',    'icon' => 'ph-credit-card', 'name' => 'Pagos'],
                15 => ['from' => 'from-sky-500',     'to' => 'to-cyan-600',    'icon' => 'ph-headphones', 'name' => 'Comunicación'],
                16 => ['from' => 'from-violet-500',  'to' => 'to-purple-600',  'icon' => 'ph-megaphone', 'name' => 'Anuncios'],
                17 => ['from' => 'from-red-600',     'to' => 'to-rose-700',    'icon' => 'ph-prohibit', 'name' => 'Prohibición'],
                18 => ['from' => 'from-red-500',     'to' => 'to-orange-600',  'icon' => 'ph-warning', 'name' => 'Advertencia'],
                19 => ['from' => 'from-green-600',   'to' => 'to-emerald-700', 'icon' => 'ph-shield-check', 'name' => 'Seguridad'],
                20 => ['from' => 'from-pink-500',    'to' => 'to-fuchsia-600', 'icon' => 'ph-target', 'name' => 'Objetivo'],
            ];

            foreach ($category_info as $id => $info) {
            ?>
                <div class="border-2 border-slate-200 rounded-2xl p-6 text-center hover:border-blue-400 transition-all">
                    <div class="w-16 h-16 rounded-xl bg-gradient-to-br <?php echo $info['from']; ?> <?php echo $info['to']; ?> flex items-center justify-center mx-auto mb-4">
                        <i class="<?php echo $info['icon']; ?> text-white ph-fill text-3xl"></i>
                    </div>
                    <h3 class="font-bold text-slate-900 mb-2">Categoría <?php echo $id; ?></h3>
                    <p class="text-sm text-slate-600 mb-2"><?php echo $info['name']; ?></p>
                    <code class="text-xs bg-slate-100 text-slate-700 px-2 py-1 rounded block"><?php echo $info['icon']; ?></code>
                </div>
            <?php } ?>
        </div>
    </div>
</section>

<?php include 'components/footer.php'; ?>
