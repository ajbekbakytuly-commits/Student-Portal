<?php require 'session.php';
if (is_logged_in()) go('dashboard.php');
render_head('Басты бет'); ?>
<section class="hero">
  <h1>Колледж оқу процесін басқару жүйесі</h1>
  <p class="lead">Сабақ кестесі, бағалар және үй тапсырмалары бір жерде. Студент, мұғалім және әкімші үшін жеке кабинеттер.</p>
  <div class="actions">
    <a class="btn" href="register.php">Тіркелу</a>
    <a class="btn btn-ghost" href="login.php">Кіру</a>
  </div>
</section>
<?php render_foot();
