document.addEventListener("DOMContentLoaded", () => {
  // Validación login (si ya la pusimos)
  const formLogin = document.querySelector("#formLogin");
  if (formLogin) {
    formLogin.addEventListener("submit", (e) => {
      const email = formLogin.email.value.trim();
      const password = formLogin.password.value.trim();
      let errores = [];

      if (email === "") errores.push("El email es obligatorio.");
      if (password === "") errores.push("La contraseña es obligatoria.");

      if (errores.length > 0) {
        e.preventDefault();
        alert(errores.join("\n"));
      }
    });
  }

  // Validación registro
  const formRegistro = document.querySelector("#formRegistro");
  if (formRegistro) {
    formRegistro.addEventListener("submit", (e) => {
      const nombre = formRegistro.nombre.value.trim();
      const email = formRegistro.email.value.trim();
      const password = formRegistro.password.value.trim();
      let errores = [];

      if (nombre === "") errores.push("El nombre es obligatorio.");
      if (email === "") errores.push("El email es obligatorio.");
      else if (!email.includes("@")) errores.push("El email no parece válido.");
      if (password.length < 4) errores.push("La contraseña debe tener al menos 4 caracteres.");

      if (errores.length > 0) {
        e.preventDefault();
        alert(errores.join("\n"));
      }
    });
  }
});
