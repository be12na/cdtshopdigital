
export function isAdmin(state) {

   return state.user && state.user.is_admin ? true : false
}
