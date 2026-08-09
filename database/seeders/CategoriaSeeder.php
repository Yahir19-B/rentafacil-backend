<?php

namespace Database\Seeders;

use App\Models\Categoria;
use Illuminate\Database\Seeder;

class CategoriaSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['Cuarto', 'Casa', 'Departamento', 'Local comercial'] as $categoria) {
            Categoria::firstOrCreate(['nombre' => $categoria]);
        }
    }
}
