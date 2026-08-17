<?php
// CADASTRO DE ITEM (CADASTRAR_ITEM.PHP)

header('Content-Type: text/html; charset=UTF-8');
require_once __DIR__ . '/../config/db.php';

$redis = Database::getRedis();
$msgSucesso = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titulo = trim($_POST['titulo'] ?? '');
    $descricao = trim($_POST['descricao'] ?? '');
    $categoria = $_POST['categoria'] ?? 'Outros';
    $valorEstimado = (float)($_POST['valor_estimado'] ?? 0);
    $localNome = $_POST['local_nome'] ?? 'Biblioteca Central';
    $cor = trim($_POST['cor'] ?? '');
    $marca = trim($_POST['marca'] ?? '');
    
    if (!empty($titulo) && !empty($descricao)) {
        $doc = [
            'titulo' => $titulo,
            'descricao' => $descricao,
            'categoria' => $categoria,
            'status' => 'encontrado',
            'valor_estimado' => $valorEstimado,
            'data_registro' => new MongoDB\BSON\UTCDateTime(),
            'local_id' => new MongoDB\BSON\ObjectId('65c200000000000000000001'),
            'cadastrado_por_usuario_id' => new MongoDB\BSON\ObjectId('65c100000000000000000001'),
            'detalhes_item' => [
                'cor' => $cor,
                'marca' => $marca,
                'tags' => array_filter(explode(',', strtolower($titulo . ',' . $categoria)))
            ],
            'historico_status' => [
                [
                    'data' => new MongoDB\BSON\UTCDateTime(),
                    'status' => 'encontrado',
                    'usuario_id' => new MongoDB\BSON\ObjectId('65c100000000000000000001'),
                    'observacao' => 'Item cadastrado via formulário web PHP.'
                ]
            ],
            'desativado' => false
        ];
        
        $newId = insertMongoDocument('itens', $doc);
        $newIdStr = (string)$newId;
        
        if ($redis) {
            try {
                $redis->zIncrBy("ranking:locais_perdas", 1, $localNome);
                $redis->hMSet("resumo:item:" . $newIdStr, [
                    'titulo' => $titulo,
                    'categoria' => $categoria,
                    'status' => 'encontrado',
                    'local' => $localNome,
                    'valor' => $valorEstimado
                ]);
            } catch (Exception $e) {}
        }
        
        $msgSucesso = "Item '{$titulo}' cadastrado com sucesso no MongoDB. Ranking do Redis atualizado.";
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadastrar Objeto | Achados e Perdidos IFMG</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="/css/style.css">
    <link rel="stylesheet" href="/public/css/style.css">
</head>
<body>

    <header class="header-nav">
        <div class="nav-container">
            <a href="index.php" class="logo-group">
                <img src="https://www.ifmg.edu.br/portal/imagens/logovertical.jpg" alt="Logo IFMG" class="logo-img">
                <div class="logo-text">Achados e Perdidos</div>
            </a>
            <ul class="nav-links">
                <li><a href="index.php" class="nav-link">Voltar ao Catálogo</a></li>
            </ul>
        </div>
    </header>

    <div class="container" style="max-width: 750px;">

        <?php if (!empty($msgSucesso)): ?>
            <div class="cache-banner hit" style="margin-bottom: 2rem;">
                <?= htmlspecialchars($msgSucesso, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <div class="hero-section">
            <h1 class="hero-title" style="font-size: 1.8rem; margin-bottom: 1.5rem;">Cadastrar Novo Objeto Encontrado</h1>
            
            <form method="POST">
                <div style="margin-bottom: 1.25rem;">
                    <label style="display: block; font-size: 0.85rem; color: var(--text-muted); margin-bottom: 0.4rem;">Título do Objeto *</label>
                    <input type="text" name="titulo" class="input-field" style="width: 100%;" placeholder="Ex: Mochila JanSport Azul" required>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.25rem;">
                    <div>
                        <label style="display: block; font-size: 0.85rem; color: var(--text-muted); margin-bottom: 0.4rem;">Categoria *</label>
                        <select name="categoria" class="input-field" style="width: 100%;" required>
                            <option value="Eletrônicos">Eletrônicos</option>
                            <option value="Documentos">Documentos</option>
                            <option value="Acessórios">Acessórios</option>
                            <option value="Material Escolar">Material Escolar</option>
                            <option value="Chaves">Chaves</option>
                            <option value="Vestuário">Vestuário</option>
                        </select>
                    </div>
                    <div>
                        <label style="display: block; font-size: 0.85rem; color: var(--text-muted); margin-bottom: 0.4rem;">Local Encontrado *</label>
                        <select name="local_nome" class="input-field" style="width: 100%;" required>
                            <option value="Biblioteca Central">Biblioteca Central</option>
                            <option value="Bloco A (Engenharias)">Bloco A (Engenharias)</option>
                            <option value="Restaurante Universitário">Restaurante Universitário</option>
                            <option value="Centro Esportivo / Ginásio">Centro Esportivo / Ginásio</option>
                            <option value="Guarita Principal">Guarita Principal</option>
                        </select>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem; margin-bottom: 1.25rem;">
                    <div>
                        <label style="display: block; font-size: 0.85rem; color: var(--text-muted); margin-bottom: 0.4rem;">Valor Estimado (R$)</label>
                        <input type="number" step="0.01" name="valor_estimado" class="input-field" style="width: 100%;" placeholder="150.00">
                    </div>
                    <div>
                        <label style="display: block; font-size: 0.85rem; color: var(--text-muted); margin-bottom: 0.4rem;">Marca</label>
                        <input type="text" name="marca" class="input-field" style="width: 100%;" placeholder="Ex: Dell, Nike">
                    </div>
                    <div>
                        <label style="display: block; font-size: 0.85rem; color: var(--text-muted); margin-bottom: 0.4rem;">Cor Predominante</label>
                        <input type="text" name="cor" class="input-field" style="width: 100%;" placeholder="Ex: Preto">
                    </div>
                </div>

                <div style="margin-bottom: 1.5rem;">
                    <label style="display: block; font-size: 0.85rem; color: var(--text-muted); margin-bottom: 0.4rem;">Descrição Detalhada *</label>
                    <textarea name="descricao" class="input-field" style="width: 100%; height: 100px;" placeholder="Informe detalhes que facilitem a identificação pelo proprietário..." required></textarea>
                </div>

                <button type="submit" class="btn-primary" style="width: 100%; justify-content: center; padding: 0.8rem;">
                    Cadastrar Objeto
                </button>
            </form>
        </div>

    </div>

</body>
</html>
