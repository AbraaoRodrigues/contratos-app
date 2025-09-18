<?php

/**
 * Funções utilitárias PNCP
 */

// Consulta genérica na API
function consultarApi($url, $maxTentativas = 3)
{
  $uas = [
    "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124 Safari/537.36",
    "Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/123 Safari/537.36",
    "Mozilla/5.0 (Macintosh; Intel Mac OS X 13_4) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16 Safari/605.1.15"
  ];

  for ($tent = 1; $tent <= $maxTentativas; $tent++) {
    $ua = $uas[array_rand($uas)];
    $ch = curl_init($url);
    curl_setopt_array($ch, [
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_MAXREDIRS      => 5,
      CURLOPT_CONNECTTIMEOUT => 10,
      CURLOPT_TIMEOUT        => 45,
      CURLOPT_SSL_VERIFYPEER => true,
      CURLOPT_ENCODING       => "",         // aceita gzip/deflate
      CURLOPT_IPRESOLVE      => CURL_IPRESOLVE_V4,
      CURLOPT_HTTPHEADER     => [
        "Accept: application/json",
        "User-Agent: $ua"
      ],
    ]);

    $res    = curl_exec($ch);
    $err    = curl_error($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($err) {
      logar("❌ cURL falhou (tent. $tent): $err");
    } else {
      if ($status === 200) {
        $json = json_decode($res, true);
        if (is_array($json)) {
          return $json;
        }
        logar("⚠️ Resposta não-JSON (tent. $tent): " . substr($res, 0, 200));
      } elseif ($status === 204) {
        logar("ℹ️ HTTP 204 (sem conteúdo) — nenhum registro nesta janela.");
        return ['data' => [], 'totalRegistros' => 0];
      } elseif ($status === 400) {
        logar("⚠️ HTTP 400 (Bad Request) — ignorando janela inválida: $url");
        return null; // usado no cron para "pular"
      } elseif ($status === 429) {
        logar("⚠️ HTTP 429 (Too Many Requests) — retry tent. $tent");
      } elseif (in_array($status, [500, 502, 503])) {
        logar("⚠️ HTTP $status (Erro servidor) — retry tent. $tent");
      } else {
        logar("⚠️ HTTP $status — corpo: " . substr($res, 0, 120));
      }
    }

    // Backoff com jitter exponencial
    $sleep = min(8, 2 ** $tent) + mt_rand(0, 1000) / 1000; // até 8s
    logar("⏳ Aguardando {$sleep}s antes da próxima tentativa...");
    usleep((int)($sleep * 1_000_000));
  }

  return false; // falhou após todas as tentativas
}

// Atalho pra baixar JSON simples
function baixarJson(string $url): ?array
{
  return consultarApi($url);
}

// Salva processo (cabeçalho) na tabela local
function salvarProcesso(PDO $pdo, array $proc): void
{
  $sql = "INSERT INTO cache_pncp_processos
              (numeroControlePNCP, objeto, orgao, uf, status, dataPublicacao, dataAbertura, dataEncerramento, json_original)
            VALUES (:num, :obj, :org, :uf, :st, :pub, :ab, :en, :json)
            ON DUPLICATE KEY UPDATE
              objeto=VALUES(objeto),
              orgao=VALUES(orgao),
              uf=VALUES(uf),
              status=VALUES(status),
              dataPublicacao=VALUES(dataPublicacao),
              dataAbertura=VALUES(dataAbertura),
              dataEncerramento=VALUES(dataEncerramento),
              json_original=VALUES(json_original)";

  $stmt = $pdo->prepare($sql);

  $stmt->execute([
    ':num'  => $proc['numeroControlePNCP'] ?? ($proc['numeroControlePNCPCompra'] ?? ''),
    ':obj'  => $proc['objetoCompra'] ?? $proc['descricaoObjeto'] ?? $proc['informacaoComplementar'] ?? '',
    ':org'  => $proc['orgaoEntidade']['razaoSocial'] ?? ($proc['razaoSocialOrgao'] ?? ''),
    ':uf'   => $proc['unidadeOrgao']['ufSigla'] ?? ($proc['uf'] ?? ''),
    ':st'   => $proc['situacaoCompraNome'] ?? ($proc['statusCompra'] ?? ''),
    ':pub'  => !empty($proc['dataPublicacaoPncp']) ? substr($proc['dataPublicacaoPncp'], 0, 10) : null,
    ':ab'   => !empty($proc['dataAberturaProposta']) ? substr($proc['dataAberturaProposta'], 0, 10) : null,
    ':en'   => !empty($proc['dataEncerramentoProposta']) ? substr($proc['dataEncerramentoProposta'], 0, 10) : null,
    ':json' => json_encode($proc, JSON_UNESCAPED_UNICODE)
  ]);
}

// Salva itens do processo (endpoint PNCP API)
function salvarItensDoProcesso(PDO $pdo, string $numeroControlePNCP): bool
{
  [$cnpj, $parte2, $sequencialAno] = explode('-', $numeroControlePNCP);
  [$sequencial, $ano] = explode('/', $sequencialAno);

  $pagina = 1;
  $ok = false;

  while (true) {
    $urlItens = "https://pncp.gov.br/api/pncp/v1/orgaos/$cnpj/compras/$ano/$sequencial/itens?pagina=$pagina&tamanhoPagina=50";
    $itens = baixarJson($urlItens);

    if (!$itens || !is_array($itens)) {
      echo "   ⚠️ Nenhum item encontrado para $numeroControlePNCP (página $pagina)\n";
      registrarFalha($pdo, $numeroControlePNCP, "Falha ao consultar itens (página $pagina)");
      return false;
    }

    if (empty($itens)) {
      break; // não há mais itens
    }

    foreach ($itens as $it) {
      $sql = "INSERT INTO cache_pncp_itens
                      (numeroControlePNCP, descricao, quantidade, valorUnitarioEstimado, valorUnitarioHomologado, json_original)
                    VALUES (:num, :desc, :qtd, :vEst, :vHom, :json)";
      $stmt = $pdo->prepare($sql);
      $stmt->execute([
        ':num'  => $numeroControlePNCP,
        ':desc' => $it['descricao'] ?? '',
        ':qtd'  => $it['quantidade'] ?? null,
        ':vEst' => $it['valorUnitarioEstimado'] ?? null,
        ':vHom' => $it['valorUnitarioHomologado'] ?? null,
        ':json' => json_encode($it, JSON_UNESCAPED_UNICODE)
      ]);
    }

    echo "   ✅ Itens salvos para $numeroControlePNCP (página $pagina)\n";
    $ok = true;

    if (count($itens) < 50) break; // última página
    $pagina++;
  }

  return $ok;
}

// Registra falha em cache_pncp_falhas
function registrarFalha(PDO $pdo, string $numero, string $motivo): void
{
  $sql = "INSERT INTO cache_pncp_falhas (numeroControlePNCP, motivo, status, tentativas, criado_em)
            VALUES (:num, :mot, 'pendente', 0, NOW())
            ON DUPLICATE KEY UPDATE
              motivo=VALUES(motivo),
              status='pendente'";
  $stmt = $pdo->prepare($sql);
  $stmt->execute([
    ':num' => $numero,
    ':mot' => $motivo
  ]);
}

// Importa processo completo (cabeçalho + itens)
// Retorna:
//   true           -> sucesso
//   "CURL_FAIL"    -> falha de comunicação
//   "HTTP400"      -> janela inválida
//   "NO_CONTENT"   -> sem dados no PNCP
//   "ERRO_CABECALHO" -> erro ao salvar cabeçalho
//   "ERRO_ITENS"   -> erro ao salvar itens
function importarProcesso(PDO $pdo, string $numeroControlePNCP)
{
  echo "🔍 Importando $numeroControlePNCP\n";

  [$cnpj, $parte2, $sequencialAno] = explode('-', $numeroControlePNCP);
  [$sequencial, $ano] = explode('/', $sequencialAno);

  // === CABEÇALHO ===
  $urlCabecalho = "https://pncp.gov.br/api/consulta/v1/orgaos/$cnpj/compras/$ano/$sequencial";
  $cabecalho = consultarApi($urlCabecalho);

  if ($cabecalho === false) {
    echo "   ❌ Falha cURL ao baixar cabeçalho de $numeroControlePNCP\n";
    registrarFalha($pdo, "CURL_FAIL", $numeroControlePNCP);
    return "CURL_FAIL";
  }

  if ($cabecalho === null) {
    echo "   ⚠️ HTTP 400 (janela inválida) para $numeroControlePNCP\n";
    registrarFalha($pdo, "HTTP400", $numeroControlePNCP);
    return "HTTP400";
  }

  if (empty($cabecalho)) {
    echo "   ℹ️ Sem conteúdo (204) para $numeroControlePNCP\n";
    registrarFalha($pdo, "NO_CONTENT", $numeroControlePNCP);
    return "NO_CONTENT";
  }

  try {
    salvarProcesso($pdo, $cabecalho);
    echo "   ✅ Cabeçalho salvo para $numeroControlePNCP\n";
  } catch (Throwable $e) {
    echo "   ⚠️ Erro ao salvar cabeçalho de $numeroControlePNCP: " . $e->getMessage() . "\n";
    registrarFalha($pdo, "ERRO_CABECALHO", $numeroControlePNCP);
    return "ERRO_CABECALHO";
  }

  // === ITENS ===
  try {
    $okItens = salvarItensDoProcesso($pdo, $numeroControlePNCP);
    if (!$okItens) {
      echo "   ⚠️ Itens indisponíveis/404 para $numeroControlePNCP\n";
      registrarFalha($pdo, "NO_ITENS", $numeroControlePNCP);
      return "NO_ITENS";
    }
    echo "   📦 Itens salvos para $numeroControlePNCP\n";
  } catch (Throwable $e) {
    echo "   ⚠️ Erro ao salvar itens de $numeroControlePNCP: " . $e->getMessage() . "\n";
    registrarFalha($pdo, "ERRO_ITENS", $numeroControlePNCP);
    return "ERRO_ITENS";
  }

  return true;
}
