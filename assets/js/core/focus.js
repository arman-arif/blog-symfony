export function focusControl(control) {
  control.scrollIntoView({block: 'center', inline: 'nearest', behavior: 'instant'});
  control.focus({preventScroll: true});
}
