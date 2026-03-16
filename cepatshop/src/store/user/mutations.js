export function SET_USER(state, payload) {
   state.user = payload
   state.loggedUser = true
   state.permissions = payload.permissions
}
export function SET_PERMISSIONS(state, payload) {
   state.permissions = payload
}
export function SET_TOKEN(state, token) {
   state.token = token
}
export function SET_CUSTOMER_LICENSE (state, payload) {
  state.customer_licenses ={...payload}
}
export function SET_WITHDRAWAL (state, payload) {
  state.customer_withdrawal ={...payload}
}

export function LOGOUT(state) {
   state.user = false
   state.token = null
   state.loggedUser = false
}
