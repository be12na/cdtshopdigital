export default function () {
   return {
      loggedUser: false,
      token: null,
      user: null,
      permissions: [],
      customer_licenses: {
         data: [],
         count: 0,
         total: 0,
         available: true
      },
      customer_withdrawal: {
         data: [],
         count: 0,
         total: 0,
         from: 1
      },
   }
}
