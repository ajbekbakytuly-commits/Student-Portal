<?php
require 'session.php';
require_login();

render_head('Microsoft Copilot');
?>
<div class="copilot-page">
  <h1>Microsoft Copilot</h1>
  <p class="muted">Ақпарат іздеуге, идея ұсынуға және оқу материалдарымен жұмыс істеуге арналған ЖИ көмекші.</p>

  <div class="copilot-hero">
    <section class="copilot-card">
      <div class="copilot-brand">
        <div class="copilot-logo"><i class="fa-solid fa-wand-magic-sparkles"></i></div>
        <div>
          <h2>Copilot</h2>
          <small>Microsoft AI</small>
        </div>
      </div>
      <p>Microsoft Copilot-ты жеке ЖИ көмекшісі ретінде пайдаланыңыз. Бұл бет Student Portal стилін сақтайды, ал Copilot-тың өзі Microsoft-тың ресми сервисінде ашылады.</p>
      <div class="copilot-actions">
        <a class="btn" href="https://copilot.microsoft.com/" target="_blank" rel="noopener noreferrer">
          <i class="fa-solid fa-arrow-up-right-from-square"></i> Copilot-ты ашу
        </a>
        <a class="btn btn-ghost" href="<?= BASE ?>dashboard.php">
          <i class="fa-solid fa-arrow-left"></i> Артқа қайту
        </a>
      </div>
      <div class="copilot-note">Толыққанды кіріктірілген чат үшін Copilot Studio ботының Web Chat / embed коды қажет.</div>
    </section>

    <section class="panel copilot-chat">
      <div class="copilot-messages">
        <div class="copilot-message bot"><b>Copilot</b><br>Сәлем! Мен ақпарат табуға, идея ойластыруға немесе оқу материалын түсінуге көмектесемін.</div>
        <div class="copilot-message user">Веб-әзірлеу пәнінен емтиханға дайындалуға көмектес.</div>
        <div class="copilot-message bot">Microsoft-тың ЖИ көмекшісімен диалогты жалғастыру үшін Copilot-ты ашыңыз.</div>
      </div>
      <div class="copilot-compose">
        <input id="copilot-demo-input" type="text" placeholder="Хабарлама жазыңыз…" maxlength="300">
        <a class="btn btn-sm" href="https://copilot.microsoft.com/" target="_blank" rel="noopener noreferrer" aria-label="Открыть Microsoft Copilot">
          <i class="fa-solid fa-paper-plane"></i>
        </a>
      </div>
    </section>
  </div>

  <div class="copilot-features">
    <div class="copilot-feature">
      <i class="fa-solid fa-lightbulb"></i>
      <b>Идеялар</b>
      <span class="muted">Жобаларға, презентацияларға және оқу тапсырмаларына көмек.</span>
    </div>
    <div class="copilot-feature">
      <i class="fa-solid fa-code"></i>
      <b>Код</b>
      <span class="muted">Кодты түсіндіру және шешім нұсқаларын іздеу.</span>
    </div>
    <div class="copilot-feature">
      <i class="fa-solid fa-book-open"></i>
      <b>Оқу</b>
      <span class="muted">Тақырыптарды қарапайым тілмен түсіндіру және сабаққа дайындалу.</span>
    </div>
  </div>
</div>
<?php render_foot(); ?>
