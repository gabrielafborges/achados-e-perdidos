<?php
// DASHBOARD PRINCIPAL (INDEX.PHP)
header('Content-Type: text/html; charset=UTF-8');
session_start();
require_once __DIR__ . '/../config/db.php';

$redis = Database::getRedis();

// Captura filtros da URL
$searchQuery = $_GET['q'] ?? '';
$categoriaFilter = $_GET['categoria'] ?? '';

$cacheKey = "";
$itemsList = [];

// Registra usuario logado no Redis (SET)
if ($redis && isset($_SESSION['user_name'])) {
    try {
        $redis->sAdd("online:usuarios", $_SESSION['user_name'] . " (" . ($_SESSION['user_tipo'] ?? 'estudante') . ")");
    } catch (Exception $e) {}
}

// CONSULTA DE ITENS
if (!empty($searchQuery) || !empty($categoriaFilter)) {
    $cacheKey = "cache:busca:" . md5($searchQuery . "|" . $categoriaFilter);
    
    if ($redis && $redis->exists($cacheKey)) {
        $cachedData = json_decode($redis->get($cacheKey), true);
        $itemsList = $cachedData ?? [];
    } else {
        $filter = ['desativado' => false];
        if (!empty($categoriaFilter)) {
            $filter['categoria'] = $categoriaFilter;
        }
        if (!empty($searchQuery)) {
            $filter['$or'] = [
                ['titulo' => new MongoDB\BSON\Regex($searchQuery, 'i')],
                ['descricao' => new MongoDB\BSON\Regex($searchQuery, 'i')]
            ];
        }
        
        $options = ['sort' => ['data_registro' => -1]];
        $rawDocs = getMongoCollection('itens', $filter, $options);
        
        $itemsList = [];
        foreach ($rawDocs as $doc) {
            $itemsList[] = [
                'id' => (string)$doc->_id,
                'titulo' => $doc->titulo ?? '',
                'descricao' => $doc->descricao ?? '',
                'categoria' => $doc->categoria ?? '',
                'status' => $doc->status ?? 'encontrado',
                'valor_estimado' => $doc->valor_estimado ?? 0,
                'data_registro' => isset($doc->data_registro) ? date('d/m/Y H:i', $doc->data_registro->toDateTime()->getTimestamp()) : 'Recente'
            ];
        }
        
        if ($redis && !empty($cacheKey)) {
            try {
                $redis->setex($cacheKey, 120, json_encode($itemsList));
            } catch (Exception $e) {}
        }
    }
} else {
    // Listagem geral
    $filter = ['desativado' => false];
    $options = ['sort' => ['data_registro' => -1], 'limit' => 12];
    $rawDocs = getMongoCollection('itens', $filter, $options);
    
    foreach ($rawDocs as $doc) {
        $itemsList[] = [
            'id' => (string)$doc->_id,
            'titulo' => $doc->titulo ?? '',
            'descricao' => $doc->descricao ?? '',
            'categoria' => $doc->categoria ?? '',
            'status' => $doc->status ?? 'encontrado',
            'valor_estimado' => $doc->valor_estimado ?? 0,
            'data_registro' => isset($doc->data_registro) ? date('d/m/Y H:i', $doc->data_registro->toDateTime()->getTimestamp()) : 'Recente'
        ];
    }
}

// Total de itens zerado se nao houver registros no banco
$totalItensCadastrados = count($itemsList);


// METRICAS DO REDIS
$rankingLocais = [];
$totalFila = 0;
$totalOnline = 0;

if ($redis) {
    try {
        $rankingLocais = $redis->zRevRange("ranking:locais_perdas", 0, 4, true);
        $totalFila = $redis->lLen("fila:reivindicacoes_pendentes");
        $totalOnline = $redis->sCard("online:usuarios");
    } catch (Exception $e) {}
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Achados e Perdidos IFMG | MongoDB & Redis</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="/css/style.css">
    <link rel="stylesheet" href="/public/css/style.css">
</head>
<body>

    <!-- Header Navigation com Busca e Autenticação -->
    <header class="header-nav">
        <div class="nav-container">
            <a href="index.php" class="logo-group">
                <img src="https://www.ifmg.edu.br/portal/imagens/logovertical.jpg" alt="Logo IFMG" class="logo-img">
                <div class="logo-text">Achados e Perdidos</div>
            </a>

            <!-- Campo de Busca Integrado no Header -->
            <form method="GET" action="index.php" class="header-search">
                <input type="text" name="q" class="input-field" placeholder="Buscar por objeto..." value="<?= htmlspecialchars($searchQuery, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
                <select name="categoria" class="input-field" style="max-width: 150px;">
                    <option value="">Categorias</option>
                    <option value="Eletrônicos" <?= $categoriaFilter === 'Eletrônicos' ? 'selected' : '' ?>>Eletrônicos</option>
                    <option value="Documentos" <?= $categoriaFilter === 'Documentos' ? 'selected' : '' ?>>Documentos</option>
                    <option value="Acessórios" <?= $categoriaFilter === 'Acessórios' ? 'selected' : '' ?>>Acessórios</option>
                    <option value="Material Escolar" <?= $categoriaFilter === 'Material Escolar' ? 'selected' : '' ?>>Material Escolar</option>
                    <option value="Chaves" <?= $categoriaFilter === 'Chaves' ? 'selected' : '' ?>>Chaves</option>
                </select>
                <button type="submit" class="btn-primary">Buscar</button>
            </form>

            <ul class="nav-links">
                <li><a href="index.php" class="nav-link active">Início</a></li>
                <li><a href="admin_fila.php" class="nav-link">Fila (<?= $totalFila ?>)</a></li>
                <li><a href="cadastrar_item.php" class="btn-primary">+ Cadastrar Item</a></li>
                
                <!-- Sistema de Login no Header -->
                <?php if (isset($_SESSION['user_id'])): ?>
                    <li style="display: flex; align-items: center; gap: 0.5rem; margin-left: 0.5rem;">
                        <span style="font-size: 0.85rem; color: var(--neon-green); font-weight: 700;">
                            <?= htmlspecialchars($_SESSION['user_name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                        </span>
                        <a href="logout.php" class="nav-link" style="color: var(--text-muted); font-size: 0.8rem; padding: 0.3rem 0.6rem;">Sair</a>
                    </li>
                <?php else: ?>
                    <li><a href="login.php" class="nav-link" style="border: 1px solid var(--border-glass);">Entrar / Login</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </header>

    <div class="container">

        <!-- Hero Section -->
        <section class="hero-section">
            <h1 class="hero-title">Achados e Perdidos IFMG</h1>
        </section>

        <!-- Stats Grid sem Status do Cache -->
        <div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));">
            <div class="stat-card">
                <div class="stat-header">
                    <span class="stat-label">Itens Cadastrados</span>
                </div>
                <div class="stat-value"><?= $totalItensCadastrados ?></div>
                <div class="stat-explanation">Total de objetos ativos registrados no banco de dados MongoDB.</div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <span class="stat-label">Fila de Atendimento (Redis)</span>
                </div>
                <div class="stat-value"><?= $totalFila ?></div>
                <div class="stat-explanation">O que é: Estrutura do tipo List no Redis onde as solicitações de devolução entram na ordem de chegada (FIFO) para validação do administrador.</div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <span class="stat-label">Usuários Online:</span>
                </div>
                <div class="stat-value"><?= $totalOnline ?></div>
                <div class="stat-explanation">Controle em tempo real de conexões ativas mantidas no conjunto Set do Redis.</div>
            </div>
        </div>

        <!-- Layout Grid: Main Cards & Sidebar Ranking -->
        <div class="layout-grid">
            
            <!-- Items Grid -->
            <div>
                <h2 style="font-family: var(--font-heading); font-size: 1.5rem; margin-bottom: 1.25rem; color: #ffffff;">Objetos em Destaque</h2>
                <div class="cards-grid">
                    <?php if (!empty($itemsList)): ?>
                        <?php foreach ($itemsList as $item): ?>
                            <div class="item-card">
                                <div>
                                    <div class="item-category"><?= htmlspecialchars($item['categoria'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
                                    <h3 class="item-title"><?= htmlspecialchars($item['titulo'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></h3>
                                    <p class="item-desc"><?= htmlspecialchars(mb_substr($item['descricao'], 0, 95, 'UTF-8'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>...</p>
                                </div>
                                <div>
                                    <div class="item-meta">
                                        <span class="badge-status status-<?= $item['status'] ?>">
                                            <?= strtoupper($item['status']) ?>
                                        </span>
                                        <span style="font-weight: 700; color: white;">R$ <?= number_format($item['valor_estimado'], 2, ',', '.') ?></span>
                                    </div>
                                    <a href="item_detalhe.php?id=<?= $item['id'] ?>" class="btn-primary" style="width: 100%; justify-content: center; margin-top: 1rem; padding: 0.5rem;">Ver Detalhes</a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div style="grid-column: 1 / -1; background: var(--bg-card); padding: 3rem; border-radius: 16px; text-align: center; color: var(--text-muted); border: 1px solid var(--border-glass);">
                            Nenhum objeto encontrado no momento. Execute o script do MongoDB para popular os dados.
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Sidebar: Redis Sorted Set Ranking com Explicacao -->
            <div>
                <div class="sidebar-panel">
                    <h3 class="panel-title">Ranking de Perdas por Local</h3>
                    
                    <div class="panel-explanation">
                        <strong>O que é o Ranking?</strong><br>
                        Estrutura Sorted Set (ZSET) no Redis sob a chave <code>ranking:locais_perdas</code>. Contabiliza e ordena dinamicamente em tempo real os locais do campus com maior incidência de objetos encontrados.
                    </div>

                    <ul class="ranking-list">
                        <?php if (!empty($rankingLocais)): ?>
                            <?php $rank = 1; foreach ($rankingLocais as $localNome => $score): ?>
                                <li class="ranking-item">
                                    <div style="display: flex; align-items: center; gap: 0.6rem;">
                                        <span class="ranking-rank"><?= $rank++ ?></span>
                                        <span><?= htmlspecialchars($localNome, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
                                    </div>
                                    <span class="ranking-score"><?= $score ?> perdas</span>
                                </li>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <li style="color: var(--text-muted); font-size: 0.85rem; padding: 0.5rem 0;">
                                Execute o script do MongoDB/Redis para visualizar a pontuação do ranking.
                            </li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>

        </div>

    </div>

</body>
</html>
