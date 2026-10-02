# ticket-sales-system

Sistema de venda de ingressos com confirmação de pagamento assíncrona via gateway externo.

- `backend/` — API em Laravel 12 (PHP 8.5, MySQL 8.4, Redis, Sail)
- `frontend/` — painel de vendas em Vue 3 + TypeScript + Vite

As duas partes sobem de forma independente. Este guia cobre o painel; o backend vem a seguir.

---

## Requisitos

Só para o painel. Não precisa de Docker, PHP nem banco de dados.

| Ferramenta | Versão |
|---|---|
| Git | qualquer recente |
| Node.js | 22.22+ ou 24.15+ (é o `engines` do `package.json`) |
| npm | 10+, vem junto com o Node |

## Instalando o painel

```bash
git clone git@github.com:UhCardoso/ticket-sales-system.git
cd ticket-sales-system/frontend
npm ci
cp .env.example .env
npm run dev
```

Abra **http://localhost:5173** — a rota `/` redireciona para `/painel`.

Três observações:

- O clone já vem na `main`, que tem o projeto completo. Por HTTPS, use
  `https://github.com/UhCardoso/ticket-sales-system.git`. O repositório é privado, então é preciso
  ter acesso.
- `npm ci` instala exatamente as versões do `package-lock.json`. Use `npm install` só para mudar
  dependência.
- O `.env` é opcional: os valores de desenvolvimento já são os padrões do código.

**Sem o backend no ar**, o painel carrega mas não mostra números — aparece um alerta de conexão e o
polling segue tentando. É o esperado: exibir zeros seria inventar número. Quando o backend subir, os
dados aparecem sozinhos.

### Scripts

| Script | O que faz |
|---|---|
| `npm run dev` | servidor de desenvolvimento com HMR |
| `npm run build` | checagem de tipos e build de produção em `dist/` |
| `npm run build-only` | build sem checar tipos |
| `npm run type-check` | só a checagem de tipos |
| `npm run preview` | serve o `dist/` já buildado |

---

## Problemas comuns

**`sh: 1: vite: not found`** — você está em `backend/`, não em `frontend/`. As duas pastas têm um
script `dev`, mas o do backend é o scaffolding do Laravel e não tem dependências instaladas. No
cabeçalho, `> dev` é o backend e `> frontend@0.0.0 dev` é o painel.

**`npm warn EBADENGINE`, ou o CLI do shadcn-vue falhando** — Node abaixo de 22. Depois de trocar de
versão, rode `npm ci` de novo.

**"Port 5173 is in use"** — o Vite sobe na 5174 e avisa qual porta usou. Para fixar outra:
`npm run dev -- --port 3000`.

**O `.env.example` não aparece no `ls`** — é arquivo oculto, só aparece com `ls -a`. O `cp` funciona
normalmente.

**O painel abre mas só mostra o alerta de falha** — o backend não está no ar, ou está em outra porta.
O proxy aponta para `API_PROXY_TARGET` (padrão `http://localhost:8000`), que precisa casar com o
`APP_PORT` do `backend/.env`.

---

## Instalando o backend

Em breve. Por ora, o resumo do ambiente local (Sail, workers, scheduler, Mailpit) está em
[Ambiente local](#ambiente-local).

---

## API

### Criar pedido

```http
POST /api/orders
Content-Type: application/json
Accept: application/json
Idempotency-Key: 3f2a9c1e-8b7d-4c1a-9e2f-5d6a7b8c9d0e

{
  "ticket_batch_id": 1,
  "quantity": 2,
  "buyer_name": "Maria Silva",
  "buyer_email": "maria@example.com",
  "buyer_document": "123.456.789-09"
}
```

O pedido nasce `pending` e **reserva** os ingressos do lote. Eles só passam a vendidos quando o pagamento é confirmado. Se não for pago dentro do prazo (`ORDER_RESERVATION_TTL`, padrão 15 min), a reserva expira.

| Status | Quando |
|---|---|
| `201 Created` | Pedido criado |
| `200 OK` | Mesma `Idempotency-Key` e mesmos dados: devolve o pedido já existente |
| `409 Conflict` | Quantidade solicitada maior que a disponível no lote |
| `422 Unprocessable Entity` | Dados inválidos, header ausente ou `Idempotency-Key` já usada com dados diferentes |

A resposta traz em `payment.checkout_url` o endereço de pagamento devolvido pelo gateway. Como não há gateway real, ele é gerado pela implementação simulada (`FakePaymentGateway`): nenhum pagamento acontece ali, o resultado chega depois pelo webhook.

### Aviso do gateway (webhook)

```http
POST /api/gateway/notifications
Content-Type: application/json
Accept: application/json

{
  "external_id": "evt_9f3a1c7b2d",
  "order_id": 12,
  "type": "approved",
  "occurred_at": "2026-09-28T14:03:11-03:00"
}
```

| Campo | O que é |
|---|---|
| `external_id` | Identificador próprio do aviso, gerado pelo gateway. É a chave de deduplicação. |
| `order_id` | Pedido a que o aviso se refere |
| `type` | `approved`, `rejected` ou `refunded` |
| `occurred_at` | Quando o aviso aconteceu. **É por esta data que o estado do pedido é decidido**, não pela ordem de chegada. |

| Status | Quando |
|---|---|
| `200 OK` | Aviso registrado e enfileirado — inclusive quando é reentrega de um aviso já conhecido |
| `422 Unprocessable Entity` | Campo ausente/inválido ou pedido inexistente |

A resposta descreve o que aconteceu com o aviso (`applied_at`, `discard_reason`), mas o normal é ela sair antes do processamento: o efeito é aplicado pelo worker.

### Resumo de vendas (painel)

```http
GET /api/dashboard/sales-summary
Accept: application/json
```

```json
{
  "data": [
    {
      "id": 1,
      "name": "Show de Rock",
      "date_time": "2026-10-25T00:33:00+00:00",
      "totals": {
        "total_quantity": 80, "sold_quantity": 4,
        "reserved_quantity": 4, "available_quantity": 72,
        "revenue": "200.00"
      },
      "batches": [
        {
          "id": 1, "name": "1º Lote", "price": "50.00",
          "total_quantity": 30, "sold_quantity": 4,
          "reserved_quantity": 4, "available_quantity": 22,
          "revenue": "200.00"
        }
      ]
    }
  ],
  "meta": { "generated_at": "2026-09-30T23:46:04+00:00", "cache_ttl": 3 }
}
```

| Campo | O que é |
|---|---|
| `sold_quantity` | Ingressos vendidos (pedidos pagos) |
| `reserved_quantity` | Aguardando pagamento — o que as reservas pendentes seguram |
| `available_quantity` | `total - reserved - sold` |
| `revenue` | Receita acumulada, como string decimal. **Quem formata é o consumidor**, não a API |
| `meta.generated_at` | Quando o snapshot em cache foi montado — **não** quando este request foi atendido |

`totals` é o agregado dos lotes do evento, somado com `bcadd`. A resposta sai do snapshot em cache (`DASHBOARD_CACHE_TTL`, padrão 3s), então dois requests dentro da janela devolvem o mesmo `generated_at` — é isso que permite ao painel declarar a idade do dado em vez de fingir tempo real.

## Simulando os avisos do gateway

O comando `gateway:notify` entrega os avisos **por HTTP**, no mesmo endpoint que um gateway real usaria, para que o caminho inteiro seja exercitado. Ele reproduz os três comportamentos declarados pelo gateway:

```bash
# aviso simples
./vendor/bin/sail artisan gateway:notify 12 --type=approved

# mesmo aviso entregue três vezes (reenvio)
./vendor/bin/sail artisan gateway:notify 12 --type=approved --duplicate=3

# a aplicação demorou a responder: o gateway ignora a resposta e reenvia
./vendor/bin/sail artisan gateway:notify 12 --type=approved --timeout

# aprovação e reembolso entregues na ordem inversa da que aconteceram
./vendor/bin/sail artisan gateway:notify 12 --out-of-order

# data/hora arbitrária, para forjar um aviso atrasado
./vendor/bin/sail artisan gateway:notify 12 --type=rejected --occurred-at="2026-09-28T10:00:00-03:00"
```

O comando imprime, por entrega, o `external_id`, a hora do evento, o status HTTP e o que a API respondeu. Quem **aplica** os avisos é o worker: sem `queue:work` nada sai do estado "registrado e enfileirado".

## Decisões de arquitetura

### Idempotência da compra — padrão Idempotency Key

**Problema.** Duplo clique, timeout de rede ou retry automático fazem a mesma compra chegar mais de uma vez. Sem proteção, cada requisição criaria um pedido e reservaria estoque de novo. O servidor não consegue distinguir "requisição repetida" de "nova compra com os mesmos dados", porque o conteúdo é idêntico.

**Solução.** Adotamos o padrão **Idempotency Key**: o cliente gera um UUID por intenção de compra e o envia no header `Idempotency-Key` em todas as tentativas. Quem sabe se duas requisições são a mesma tentativa é o cliente, então é ele que identifica.

- **Chave nova:** o pedido é criado (`201`).
- **Chave repetida com os mesmos dados:** nada é criado e a API devolve o pedido original (`200`). O cliente pode repetir a chamada com segurança.
- **Chave repetida com dados diferentes:** a API responde `422`. Isso indica bug no cliente (chave não renovada), e devolver o pedido antigo esconderia o erro.
- **Duas requisições simultâneas com a mesma chave:** a coluna `orders.idempotency_key` tem índice único. A segunda gravação falha, a transação dela desfaz a reserva e a API devolve o pedido da primeira. Por isso a garantia vem do banco, não da checagem em código.

**Referências.** É o mecanismo usado por APIs de pagamento como [Stripe](https://docs.stripe.com/api/idempotent_requests), PayPal e Adyen. O header está em processo de padronização na IETF: [*The Idempotency-Key HTTP Header Field*](https://datatracker.ietf.org/doc/draft-ietf-httpapi-idempotency-key-header/). O draft também recomenda `422` para reuso da chave com payload diferente.

**Diferenças em relação à implementação do Stripe, e por quê:**

| Stripe | Aqui | Motivo |
|---|---|---|
| Tabela genérica `idempotency_keys` guardando a resposta de qualquer endpoint | Coluna no próprio `orders` | Só um endpoint precisa de idempotência. A resposta é reconstruída a partir do pedido, que é a fonte da verdade. |
| Responde `409` enquanto a requisição original ainda está em processamento | Não se aplica | A criação é uma transação curta, e o índice único resolve a corrida. |
| Chaves expiram (~24h) | Não expiram | O volume é de uma chave por pedido, e manter a chave preserva o rastro de auditoria. |
| Chave única por conta autenticada | Única no sistema | Não há autenticação de comprador. A colisão de UUID v4 é desprezível. |

**Implementação:** `backend/app/Services/OrderService.php`, testes em `backend/tests/Feature/CreateOrderTest.php`.

> A mesma ideia, do lado de quem recebe (*Idempotent Receiver*, de *Enterprise Integration Patterns*), vale para os avisos do gateway de pagamento. Lá o papel da chave é do identificador do próprio aviso.

### Expiração de reservas

**Problema.** A compra reserva os ingressos antes do pagamento. Um pedido que nunca é pago seguraria esses ingressos para sempre, e o lote pareceria esgotado sem ter vendido tudo.

**Solução.** Cada pedido nasce com `expires_at = criação + ORDER_RESERVATION_TTL` (padrão **15 minutos**). O comando `orders:expire` roda **a cada minuto** pelo scheduler: marca como `expired` os pedidos `pending` vencidos e devolve a quantidade reservada ao lote. Os ingressos voltam a ficar disponíveis na hora.

**Por que 15 minutos.** É tempo suficiente para concluir um pagamento (PIX, cartão com 3-D Secure) e curto o bastante para não travar estoque num pico de vendas. Como o valor é configurável por env, dá para usar `ORDER_RESERVATION_TTL=1` e ver a expiração acontecer localmente.

**Garantias:**

- **Sem liberação dupla.** Cada pedido é expirado na própria transação, com `lockForUpdate()` no pedido e no lote. Se o comando rodar duas vezes sobre o mesmo pedido, a segunda vê que ele não está mais `pending` e não faz nada.
- **Pagamento e expiração ao mesmo tempo.** A expiração relê o pedido com lock antes de agir. Se o pagamento foi confirmado entre a consulta do scheduler e a expiração, o pedido não é mais `pending` e fica como está.
- **Transições centralizadas.** Toda mudança de status passa por `Order::transitionTo()`, que consulta as transições permitidas no enum `OrderStatus`. Um pedido `expired` não pode virar `paid`, por exemplo.
- **Falha isolada.** Um erro ao expirar um pedido é registrado em log e não impede os demais; o pedido é tentado de novo na execução seguinte.

**Por que scheduler e não um Job agendado por pedido.** Um Job com `delay` por pedido dependeria da fila estar saudável no momento exato do vencimento, e um Job perdido deixaria a reserva presa para sempre. A varredura periódica se autocorrige: qualquer pedido vencido é pego na próxima execução, independentemente do que aconteceu antes. O índice `(status, expires_at)` em `orders` mantém essa consulta barata.

**Ambiente local.** O serviço `scheduler` do `compose.yaml` roda `php artisan schedule:work`. Sem ele, as reservas nunca expiram. Para rodar manualmente: `./vendor/bin/sail artisan orders:expire`.

**Implementação:** `backend/app/Services/OrderService.php` (`expire`), `backend/app/Console/Commands/ExpireOverdueOrders.php`, testes em `backend/tests/Feature/ExpireOrdersTest.php`.

### Recebimento dos avisos — padrão Inbox (store-and-forward)

**Problema.** O gateway considera falha **qualquer resposta acima de 3 segundos** e reenvia o aviso. Os efeitos de um pagamento aprovado são caros: emitir ingressos, enviar dois e-mails e registrar a venda no sistema financeiro, que sozinho leva de 2 a 5 segundos. Fazer isso dentro do request é timeout garantido — e um timeout não é só lentidão, ele **produz uma reentrega**. Processar no request transforma cada chamada lenta em trabalho duplicado.

**Solução.** O endpoint faz três coisas e nada mais:

```
persiste o aviso bruto  →  enfileira o processamento  →  responde 200
```

Esse é o **padrão Inbox** (o espelho do *Transactional Outbox*), também chamado de *store-and-forward*. O aviso vira uma linha em `gateway_notifications` antes de qualquer decisão; aplicar o efeito é trabalho do worker. O request fica em dezenas de milissegundos, longe do limite de 3s, independentemente de quanto o financeiro demore.

- O despacho do Job usa **`afterCommit()`**. Sem isso o worker pode buscar o aviso antes do commit e não encontrar nada.
- A reentrega de um aviso **ainda não resolvido** também enfileira. É o caminho de recuperação se o processamento da primeira entrega não chegou ao fim.
- Nenhum `ShouldBeUnique` no Job: ele descartaria silenciosamente um aviso que chegasse durante o processamento de outro do mesmo pedido, e o pedido ficaria com um aviso órfão. A correção é o `lockForUpdate`, não a deduplicação de Jobs — um drain repetido é um no-op barato.

**Referências.** [Transactional Outbox/Inbox](https://microservices.io/patterns/data/transactional-outbox.html) e [Queue-Based Load Leveling](https://learn.microsoft.com/en-us/azure/architecture/patterns/queue-based-load-leveling).

**Implementação:** `backend/app/Http/Controllers/GatewayNotificationController.php`, `backend/app/Jobs/ProcessGatewayNotification.php`.

### Avisos repetidos — padrão Idempotent Receiver

**Problema.** O mesmo aviso pode ser entregue mais de uma vez, seja por reenvio do gateway, seja porque ele não recebeu nossa resposta em 3s. Aplicar duas vezes uma aprovação venderia o estoque duas vezes, somaria a receita duas vezes e emitiria ingressos duplicados.

**Solução.** É o inverso da idempotência da compra: lá quem gera a chave é o cliente, porque só ele sabe se duas requisições são a mesma intenção; aqui **o remetente já entrega a chave pronta** — cada aviso traz seu identificador próprio. Então não há nada a inventar: `gateway_notifications.external_id` tem **índice único**.

- Inserção nova: o aviso é registrado e enfileirado.
- Reentrega: a gravação viola o índice único, a exceção é capturada e a API devolve o aviso já registrado com `200`. Nenhum efeito é aplicado de novo.
- **A garantia é do banco, não de um `if (existe)`** — duas reentregas simultâneas passariam pela checagem juntas.

**Referências.** [*Idempotent Receiver*](https://www.enterpriseintegrationpatterns.com/patterns/messaging/IdempotentReceiver.html), de *Enterprise Integration Patterns* (Hohpe & Woolf); em sistemas de fila o mesmo padrão aparece como *Idempotent Consumer*.

**Implementação:** `backend/app/Services/GatewayNotificationService.php` (`record`), teste `the same notification delivered twice is applied once`.

### Avisos fora de ordem — marca d'água e replay por `occurred_at`

**Problema.** Deduplicar garante "cada aviso uma vez"; **não** garante "na ordem certa". Este é um problema separado, e é onde mais se escorrega. Os avisos podem chegar fora da ordem em que aconteceram — por isso cada um traz a própria data/hora. Um `rejected` antigo chegando depois de um `approved` novo rebaixaria um pedido já pago.

**Solução.** Duas peças, ambas necessárias:

1. **Marca d'água** (*high-water mark*). `orders.last_notification_at` guarda o `occurred_at` do último aviso aplicado. Aviso mais antigo que isso é **registrado e não aplicado**, com `discard_reason = older_than_last_applied`. Decidir o estado pela data do evento, e não pela ordem de chegada, é resolução de conflito por *Last-Write-Wins*.

2. **Replay dos não aplicados.** A marca d'água sozinha tem um furo real. Se `approved` (t1) e `refunded` (t2) invertem na rede, o `refunded` chega primeiro com o pedido em `pending` — e `pending → refunded` é transição inválida. Descartá-lo faria o pedido terminar **`paid` enquanto o gateway diz reembolsado**.

   Por isso o processamento não olha só o aviso que chegou: ele aplica **todos os avisos ainda não resolvidos daquele pedido, em ordem de `occurred_at`**. No exemplo, ao processar `approved` (t1) o replay encontra o `refunded` (t2) parado e aplica os dois em sequência — `pending → paid → refunded` — com os efeitos de estoque corretos em cada passo. É um *fold* sobre o log de avisos, a mesma ideia de projeção de event sourcing, em escala trivial: são um a três avisos por pedido.

**Aviso adiantado x aviso incoerente.** Um aviso cuja transição é inválida só continua pendente enquanto o pedido está `pending`, o único estado que ainda pode avançar. Em qualquer estado final, nenhum aviso futuro tornaria a transição possível, então ele é descartado com `discard_reason = order_status_does_not_allow` e registrado em log.

**Onde a marca d'água e o marcador de aplicação são gravados.** Na **mesma transação** da mudança de estado. Se fossem escritas separadas, uma falha entre elas faria o aviso ser reprocessado contra um pedido que já mudou — e ser descartado como incoerente.

**Limitação conhecida.** Uma aprovação que chega depois de a reserva ter expirado (o pagamento saiu, mas o estoque já voltou ao lote e pode ter sido vendido a outra pessoa) é registrada, descartada e logada como `warning`. O tratamento correto seria iniciar um reembolso no gateway, o que exige uma capacidade que o gateway simulado não tem.

**Referências.** [*High-Water Mark*](https://martinfowler.com/articles/patterns-of-distributed-systems/high-watermark.html), em *Patterns of Distributed Systems*; *Last-Write-Wins* como resolução de conflito, em *Designing Data-Intensive Applications* (Kleppmann, cap. 5).

**Implementação:** `backend/app/Services/GatewayNotificationService.php` (`apply`, `settle`), `Order::hasNewerNotificationThan()`, testes `older notification arriving later does not overwrite newer state` e `refund delivered before the approval still ends refunded`.

### Reembolso — transação de compensação

**Problema.** Reembolso não é apagar a venda: é desfazer os efeitos dela. Os ingressos precisam voltar ao lote e os já emitidos precisam deixar de valer.

**Solução.** É uma **transação de compensação** (a etapa de compensação de uma *Saga*): uma quarta transição de estoque, simétrica à do pagamento.

| | Estoque | Receita | Ingressos |
|---|---|---|---|
| `approved` | `reserved -= q`, `sold += q` | `+= total` (`bcadd`) | emitidos |
| `refunded` | `sold -= q` | `-= total` (`bcsub`) | `invalidated_at = agora` |

- Tudo numa transação, com `lockForUpdate()` no pedido **e** no lote — o mesmo padrão da compra e da expiração.
- A transição de status é validada **antes** de mexer nos contadores. Na ordem inversa, um reembolso sobre pedido não pago tentaria decrementar `sold_quantity` de zero e bateria na coluna `unsigned` antes de a regra de negócio ser consultada.
- Os ingressos são **invalidados, não apagados**: um ingresso reembolsado apresentado na portaria precisa ser recusado com motivo, não "não existir".
- A emissão se recusa a rodar para pedido que não está `paid`. Sem isso, um `IssueTickets` atrasado — reembolso aplicado antes da emissão, no cenário fora de ordem — criaria ingressos válidos para um pedido reembolsado.

**Referências.** [Compensating Transaction](https://learn.microsoft.com/en-us/azure/architecture/patterns/compensating-transaction); o conceito vem de *Sagas* (Garcia-Molina & Salem, 1987).

**Implementação:** `backend/app/Services/OrderService.php` (`refund`), `backend/app/Models/TicketBatch.php` (`returnSale`), testes em `backend/tests/Feature/RefundTest.php`.

### Efeitos pós-pagamento — um Job por efeito

**Problema.** Um pagamento aprovado dispara quatro efeitos: emitir os ingressos (um código único por ingresso, com QR Code em PDF), enviar o comprovante, enviar os ingressos e registrar a venda no sistema financeiro. Todos podem falhar ou demorar, e o financeiro falha em ~20% das chamadas. Se fossem um Job só, o retry causado pelo financeiro reenviaria os e-mails.

**Solução.** *Fan-out* explícito: o `OrderService` despacha **um Job por efeito**, cada um com a própria política de retry. Uma falha só repete o próprio efeito.

```
markAsPaid (commit) ──┬── IssueTickets ──> SendTicketsEmail   (chain: o e-mail precisa dos ingressos)
                      ├── SendReceiptEmail                    (fila default)
                      └── RegisterInFinancialSystem           (fila financial)
```

- **Despacho explícito no Service, não evento + listeners.** Lendo `OrderService::dispatchPendingEffects()` você vê exatamente o que acontece depois de um pagamento.
- **`afterCommit()`.** O `markAsPaid` roda dentro da transação que aplica o aviso do gateway. Sem `afterCommit`, o worker poderia pegar o Job antes do commit e ler o pedido ainda `pending`.
- **Único encadeamento:** emissão → e-mail de ingressos, porque é uma dependência real de dados. Os demais efeitos são independentes e rodam em paralelo.
- **Pedido reembolsado antes dos Jobs rodarem.** Os e-mails não saem: os ingressos já foram invalidados. O registro no financeiro sai, porque a venda aconteceu.
- **O PDF é gerado na hora do envio, não salvo em disco.** Os códigos no banco são a fonte da verdade e o PDF é derivado deles, então não há arquivo para manter sincronizado em retry ou reembolso. Os códigos são UUID v4, impossíveis de adivinhar, o que importa porque o QR Code é a credencial de entrada.

**Implementação:** `backend/app/Services/OrderService.php` (`markAsPaid`, `dispatchPendingEffects`), `backend/app/Jobs/`, `backend/app/Tickets/TicketPdf.php`, testes em `backend/tests/Feature/PostPaymentEffectsTest.php`.

### Efeitos sem repetição — marcador por efeito e idempotência nas duas pontas

**Problema.** A fila é *at-least-once*: um Job pode re-executar depois de já ter feito o trabalho (retry, worker reiniciado, timeout da fila). Mesmo com o webhook deduplicado, o retry do próprio Job reenviaria e-mail ou registraria a venda de novo. O requisito é explícito: o comprador nunca recebe e-mail repetido, e uma venda nunca é registrada duas vezes no financeiro.

**Solução.** Um marcador persistido **por efeito** em `orders`, que o Job consulta antes de agir e grava logo depois do sucesso:

| Efeito | Marcador |
|---|---|
| Emissão | `tickets_issued_at` (com `lockForUpdate` no pedido) |
| Comprovante | `receipt_sending_at` / `receipt_sent_at` (ver próxima seção) |
| Ingressos | `tickets_sending_at` / `tickets_sent_at` |
| Financeiro | `financial_registered_at` + `financial_reference` |

Além disso, o middleware `WithoutOverlapping` por pedido impede que duas cópias do mesmo Job passem juntas pela checagem.

**O furo que o marcador sozinho não fecha.** Entre "o serviço externo aceitou" e "o marcador foi gravado" existe uma janela. Se o worker morrer ali, o retry encontra o marcador vazio e repete. Não dá para colocar a chamada externa e a gravação no banco na mesma transação. A saída é ter idempotência **também do lado de quem recebe**:

- **Financeiro:** o id do pedido vai como chave de idempotência, e o sistema financeiro devolve o registro original em vez de criar outro. É o mesmo *Idempotent Receiver* dos avisos do gateway, agora com a gente no papel de remetente. Com isso a garantia fica completa.
- **E-mail:** SMTP não tem chave de idempotência. Esse caso tem tratamento próprio, na seção seguinte.

`payment_reference` funciona como o mesmo tipo de marcador para a abertura da cobrança no gateway: uma compra repetida reaproveita a cobrança existente em vez de abrir uma segunda.

**Implementação:** `backend/app/Jobs/RegisterInFinancialSystem.php`, `backend/app/Contracts/FinancialSystemInterface.php`, testes `rerunning the jobs does not repeat any effect` e `financial retry after a lost success does not register twice`.

### E-mail nunca duplicado — reivindicação e estado "em dúvida"

**Problema.** **Entrega exatamente uma vez** é impossível por SMTP. O servidor pode aceitar a mensagem e o worker morrer antes de registrar isso, e não há chave de idempotência que faça o servidor descartar um segundo envio. Qualquer ordem de operações tem um modo de falha:

| Ordem | Se o worker morrer no meio | Garantia |
|---|---|---|
| envia → marca | reenvia | *at-least-once*: pode **duplicar** |
| marca → envia | nunca envia | *at-most-once*: pode **perder** |

**Solução.** Três estados em vez de dois, e o caso ambíguo tratado explicitamente:

```
(nada) ──reivindica──> sending ──servidor aceitou──> sent
                          │
                          └── sem confirmação ──> EM DÚVIDA: nunca reenvia sozinho
```

1. **Reivindicação atômica.** `UPDATE orders SET receipt_sending_at = now() WHERE id = ? AND receipt_sending_at IS NULL`. Só um processo consegue. A garantia é do UPDATE condicional, não de um `if` lido antes.
2. **Sucesso:** grava `receipt_sent_at`.
3. **Falha que certamente não entregou:** libera a reivindicação e deixa a fila fazer o retry. O critério é o protocolo SMTP: antes do comando `DATA` o servidor não tem a mensagem, então não há como ela ter sido entregue. O Symfony Mailer anexa à exceção a transcrição dos comandos, e é nela que o Job verifica se o `DATA` foi enviado.
4. **Falha a partir do `DATA`, ou reivindicação sem confirmação (worker morreu):** o e-mail fica **em dúvida**. Ele não é reenviado automaticamente: o Job registra `warning` no log e o comando `orders:reconcile` lista os pedidos nessa situação para conferência humana.

**Por que esta escolha.** O requisito diz "nunca" receber e-mail repetido, e isso é cumprido ao pé da letra. O caso raro e ambíguo não vira um e-mail duplicado nem um e-mail perdido em silêncio: vira um item visível para alguém conferir no log do provedor.

**Alternativas descartadas:**
- **API de e-mail com chave de idempotência** (alguns provedores transacionais aceitam `Idempotency-Key`). Resolveria de fato, mas não funciona com Mailpit/SMTP e amarra o projeto a um provedor.
- **`Message-ID` determinístico.** O Gmail deduplica mensagens com o mesmo `Message-ID`, mas isso é comportamento de implementação, não do padrão, e o Mailpit mostra as duas. É mitigação, não garantia.

**Mailables não são `ShouldQueue`.** Se fossem, o `send()` só enfileiraria outro Job, e o marcador seria gravado sem o e-mail ter saído. Quem é enfileirado e faz o retry é o Job que envia.

**Implementação:** `backend/app/Jobs/SendOrderEmail.php` (base dos dois e-mails), `Order::claimEmail()`, testes em `backend/tests/Feature/EmailInDoubtTest.php`.

### Sistema financeiro lento e instável — retry com backoff e fila isolada

**Problema.** O financeiro leva de 2 a 5 segundos por chamada e falha em ~20% delas. Uma falha não pode perder a venda, e a lentidão não pode atrasar o resto do sistema.

**Solução.**

- **Retry com backoff crescente.** 10 tentativas, com esperas de 5s, 15s, 30s, 60s, 2 min, 5 min e depois 10 min. Com 20% de falha, a chance de esgotar todas é 0,2¹⁰, cerca de 1 em 10 milhões. As esperas somam ~40 minutos, o que permite atravessar uma indisponibilidade prolongada.
- **Fila própria (`financial`) com worker dedicado.** Com uma fila só, cada chamada prenderia o worker por ~3,5s, e os e-mails e a emissão de ingressos de todas as vendas esperariam atrás do financeiro. Isolado, um financeiro lento ou fora do ar não atrasa nenhum e-mail. É o padrão *Bulkhead*: compartimentar para que um componente lento não afunde os outros. Para ganhar vazão, basta escalar só esse worker: `sail up -d --scale worker-financial=3`.
- **Esgotou as tentativas:** log `critical` e a reconciliação (seção seguinte) tenta de novo. A venda nunca some em silêncio.

**O simulador.** `SimulatedFinancialSystem` dorme entre `FINANCIAL_MIN_DELAY_MS` e `FINANCIAL_MAX_DELAY_MS` (padrão 2000–5000) e falha em `FINANCIAL_FAILURE_RATE` das chamadas (padrão 0.2). **Metade das falhas acontece depois de registrar a venda**, simulando a resposta perdida no caminho de volta. É o caso que só a chave de idempotência protege, e ele precisa acontecer para a garantia ser exercitada de verdade. Nos testes, ele é trocado por um dublê determinístico (`tests/Doubles/FakeFinancialSystem.php`): instantâneo e falhando só quando o teste manda.

**Referências.** [Retry](https://learn.microsoft.com/en-us/azure/architecture/patterns/retry) e [Bulkhead](https://learn.microsoft.com/en-us/azure/architecture/patterns/bulkhead).

**Implementação:** `backend/app/Jobs/RegisterInFinancialSystem.php`, `backend/app/Financial/SimulatedFinancialSystem.php`, teste `unstable financial system ends with the sale registered once`.

### Nenhuma venda sem registro — reconciliação periódica

**Problema.** O retry cobre falha **transitória**, mas não cobre o Job que **deixou de existir**:

- o Job esgotou as tentativas (financeiro fora do ar por mais de ~40 min);
- o Redis estava indisponível no momento do despacho, depois do commit da venda;
- o Redis reiniciou e perdeu Jobs (no compose, ele persiste por snapshot, não a cada escrita).

Nos três casos a venda existe no banco, mas o comprador fica sem ingresso ou a venda fica sem registro no financeiro. É o clássico problema da **escrita dupla** (*dual write*): gravar no banco e publicar na fila são operações em sistemas diferentes, sem transação comum.

**Solução.** **Os marcadores, e não a fila, são o registro do que falta fazer.** `paid_at` e os marcadores vazios são gravados **no mesmo commit** da venda. Então o banco sempre sabe quais efeitos estão pendentes, e a fila é só o atalho para executá-los rápido. É um *Transactional Outbox* implícito: o pedido é a própria caixa de saída.

O comando `orders:reconcile` roda **a cada 5 minutos** pelo scheduler:

1. busca pedidos pagos há mais de `ORDER_RECONCILE_AFTER` minutos (padrão 60) com algum efeito pendente;
2. chama o **mesmo** `dispatchPendingEffects()` usado logo após o pagamento, que despacha só os efeitos cujo marcador está vazio;
3. lista os e-mails em dúvida, **sem reenviá-los**.

- **Por que é seguro redespachar:** todo Job é idempotente. Redespachar um efeito que ainda estava na fila é um no-op.
- **Por que 60 minutos:** é mais que o ciclo completo de retries do financeiro (~40 min), para a reconciliação não competir com as tentativas normais.
- **Por que varredura e não só `failed_jobs`:** `failed_jobs` só registra o Job que falhou, não o que se perdeu. A varredura se autocorrige a partir do estado do banco, independentemente do que aconteceu com a fila. É o mesmo raciocínio da expiração de reservas.

**Implementação:** `backend/app/Console/Commands/ReconcilePostPaymentEffects.php`, `Order::scopeWithPendingEffects()`, testes em `backend/tests/Feature/ReconcilePostPaymentEffectsTest.php`.

### Painel de vendas — leitura em cache, nunca pelo caminho do lock

O cenário declarado é ~30 pessoas com o painel aberto atualizando a cada poucos segundos, enquanto milhares compram. O mesmo dado tem então dois requisitos opostos, e confundi-los derruba o sistema no pico:

- **Caminho de compra** — precisa ser exato. Lê com `lockForUpdate()`, dentro de transação.
- **Painel** — tolera alguns segundos de atraso. Lê de cache, **nunca** com lock.

Se o painel lesse pelo caminho do lock, 30 clientes em polling entrariam na fila de lock do caminho crítico de venda.

- **Snapshot em cache no Redis, TTL curto** (`DASHBOARD_CACHE_TTL`, padrão 3s). Os 30 clientes batem no cache; só o primeiro de cada janela chega ao MySQL. Coberto por teste: o segundo request não emite nenhuma query (`SalesDashboardTest::test_serves_repeated_requests_from_cache_without_touching_the_database`).
- **Lê os contadores denormalizados do lote**, nunca `COUNT`/`SUM` agregando `orders` — que colidiria justamente com a escrita sob contenção.
- **`generated_at` é gerado dentro do closure do cache**, então data o snapshot e não o request que o encontrou pronto. É o que o painel usa para dizer "atualizado há 7s" com honestidade.
- **Sem invalidação de cache**, só TTL. O painel tolera a defasagem; invalidar a cada venda devolveria a carga ao banco no pico, que é o problema que o cache existe para resolver.
- **Resource separado** (`EventSalesSummaryResource`) do resource do caminho de compra (`TicketBatchResource`), que deliberadamente esconde os contadores. São dois consumidores com contratos diferentes.

**Implementação:** `backend/app/Services/SalesDashboardService.php`, `backend/app/Http/Controllers/SalesDashboardController.php`, testes em `backend/tests/Feature/SalesDashboardTest.php`.

Descartado SSE/WebSocket: o ganho real é pequeno diante do custo de mais um serviço no ambiente local.

### Frontend (`frontend/`)

A instalação está em [Instalando o painel](#instalando-o-painel). Esta seção é o desenho.

Em dev o painel chama `/api` na própria origem e o **proxy do Vite** encaminha para o backend (`API_PROXY_TARGET`, padrão `http://localhost:8000` — precisa casar com o `APP_PORT` do `backend/.env`). Assim não há CORS no caminho. Em produção, aponte `VITE_API_BASE_URL` para a URL real da API.

| Variável | Para que |
|---|---|
| `VITE_API_BASE_URL` | Base da API (`/api` em dev) |
| `API_PROXY_TARGET` | Destino do proxy de dev |
| `VITE_POLL_INTERVAL` | Intervalo do polling, em ms (padrão 5000) |
| `VITE_API_TIMEOUT` | Timeout de cada request, em ms |

**Camadas** — `view → composable → service → AxiosClient`, cada uma só conhecendo a de baixo. A regra de ouro: **a view nunca fala com o axios.** Quem fala é sempre um service, e `services/` é a única camada que importa a instância do axios.

```
frontend/src/
├── main.ts · App.vue            Bootstrap e o <RouterView />
├── config/                      AxiosClient.ts (instância única + ApiError) · Router.ts
├── models/                      Contratos da API; genéricos em shared/
├── services/                    Uma classe por recurso da API
├── composables/                 useSalesDashboard · usePolling · useNow
├── components/                  ui/ (shadcn) · common/ (domínio) · charts/ (medidor)
├── views/                       Uma pasta por área: Dashboard/
├── utils/                       formatMoney.ts · sumMoney.ts · cn.ts
└── assets/css/main.css          Tailwind v4 e os tokens do tema
```

| Camada | Papel |
|---|---|
| `config/` | Infraestrutura: `AxiosClient.ts` (instância única + interceptor, normaliza falha em `ApiError` separando `aborted` do resto) e `Router.ts` (rotas com `import()` dinâmico). |
| `models/` | Só os tipos do que entra e sai da API, em snake_case como o Laravel manda. Sem comportamento. Genéricos em `models/shared/`. |
| `services/` | Uma classe por recurso da API. Conhece path, verbo e payload; devolve `response.data`. É a única camada que importa o `AxiosClient`. |
| `composables/` | Polling e os derivados de apresentação (frações da barra, esgotado, totais globais). Instancia o service. |
| `components/` | Por natureza, não por tela: `ui/` (primitivos shadcn-vue), `common/` (compostos de domínio), `charts/` (o medidor). |
| `views/` | Uma pasta por área (`Dashboard/`). Monta a tela a partir do composable e dos componentes. |
| `utils/` | Funções puras, sem Vue e sem estado: `formatMoney.ts`, `sumMoney.ts`, `cn.ts`. |

**Decisões do painel:**

- **Polling, não WebSocket** — pelo mesmo motivo do backend: um serviço a menos no ambiente local.
- **Pausa com a aba oculta** (`visibilitychange`), e ao voltar refaz a leitura na hora em vez de esperar o próximo tick. Trinta painéis esquecidos abertos param de perguntar.
- **Sem requests sobrepostos** — um poll ainda no ar suprime o próximo tick; `AbortController` cancela o anterior e no unmount. Um request abortado não é erro: é um poll que o seguinte substituiu.
- **Falha de poll nunca apaga o painel.** O alerta aparece acima da última leitura boa, e o indicador diz de quando ela é. Apagar a tela porque um GET falhou é o pior que um painel pode fazer. Mas **sem nenhuma leitura válida o painel não mostra tile nenhum** — exibir zeros seria apresentar número inventado como fato.
- **Skeleton só na primeira carga.** Polls seguintes atualizam no lugar; skeleton a cada 5s viraria estroboscópio.
- **Dinheiro nunca vira float.** A API manda string decimal e ela é mantida assim; a soma dos eventos para os totais globais usa inteiros em centavos via `BigInt` (`utils/sumMoney.ts`), que é o `bcadd` desta camada. Fica separado de `utils/formatMoney.ts` de propósito: um arquivo é aritmética, o outro é texto para humano. `toNumber` existe só para largura de barra.
- **Medidor de estoque** — só vendido e aguardando recebem matiz; disponível é a trilha vazia, porque "ainda não aconteceu" não é categoria. O par de cores foi validado por script (banda de luminosidade, piso de croma, separação para daltonismo e contraste) contra a superfície do card nos dois temas. A legenda está sempre presente e a tabela repete todos os números, então nada depende só da cor.

### Ambiente local

Suba sempre pelo Sail (`./vendor/bin/sail up -d`), nunca com `docker compose up` direto. O script `sail` exporta `WWWUSER`/`WWWGROUP` com o seu uid. Sem isso, o usuário do container perde o mapeamento, os workers não sobem e a aplicação não consegue escrever em `storage/logs`.

Serviços além de `laravel.test`, `mysql`, `redis` e `phpmyadmin`:

| Serviço | Comando | Sem ele |
|---|---|---|
| `worker` | `queue:work --queue=default` | os avisos do gateway são registrados e nunca aplicados; ingressos e e-mails não saem |
| `worker-financial` | `queue:work --queue=financial` | as vendas nunca são registradas no financeiro |
| `scheduler` | `schedule:work` | reservas nunca expiram e efeitos perdidos nunca são reconciliados |
| `mailpit` | — | os e-mails não têm para onde ir |

Para processar as filas manualmente:

```bash
./vendor/bin/sail artisan queue:work --queue=default,financial --stop-when-empty
./vendor/bin/sail artisan orders:reconcile
```

**E-mails:** abra o Mailpit em **http://localhost:8025**. Depois de aprovar um pedido com `gateway:notify`, chegam o comprovante e o e-mail de ingressos, com o PDF anexado.

**Ver o financeiro falhando e o retry acontecendo:** coloque `FINANCIAL_FAILURE_RATE=0.7` no `.env`, reinicie os workers (`./vendor/bin/sail restart worker worker-financial`), aprove alguns pedidos e acompanhe `./vendor/bin/sail logs -f worker-financial`. Aparecem `FAIL` seguidos de nova tentativa, até `DONE`, e cada venda termina registrada uma única vez.

Fila e cache em **Redis**, não em `database` — a fila no MySQL concorreria com o caminho de compra, que é justamente o que está sob contenção de escrita no pico.
