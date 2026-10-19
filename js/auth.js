// SnapVenture - Admin auth pages

// Show / hide password toggles. aria-controls can list several ids,
// so one toggle can reveal both "password" and "confirm password".
document.querySelectorAll(".password-toggle").forEach(function (button) {
  var inputs = button
    .getAttribute("aria-controls")
    .split(" ")
    .map(function (id) {
      return document.getElementById(id);
    });
  var label = inputs.length > 1 ? "passwords" : "password";

  button.addEventListener("click", function () {
    var show = inputs[0].type === "password";

    inputs.forEach(function (input) {
      input.type = show ? "text" : "password";
    });
    button.setAttribute("aria-pressed", show ? "true" : "false");
    button.setAttribute("aria-label", (show ? "Hide " : "Show ") + label);

    // Keep the cursor in the field so typing can continue
    var input = inputs[0];
    input.focus();
    input.setSelectionRange(input.value.length, input.value.length);
  });
});

// Field errors: clear one as soon as the user edits that field
document.querySelectorAll('.field input[aria-invalid="true"]').forEach(function (input) {
  input.addEventListener(
    "input",
    function () {
      input.removeAttribute("aria-invalid");
      var error = document.getElementById(input.id + "-error");
      if (error) {
        error.hidden = true;
      }
    },
    { once: true }
  );
});

// Put the cursor in the first field with an error
var firstInvalid = document.querySelector('.field input[aria-invalid="true"]');
if (firstInvalid) {
  firstInvalid.focus();
}
