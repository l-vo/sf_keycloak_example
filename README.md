# Symfony demo with Keycloak

This is the code used in the talk about Symfony in Keycloak at Symfony live Paris 2022.

The configuration is close to the project on the main branch apart:
- [Traefik](https://doc.traefik.io/traefik/) and [Mkcert](https://github.com/FiloSottile/mkcert) are used to have a secure keycloak.tld domain
- Symfony proxy (with Symfony local web server) is used to have a secure symfony-demo.wip domain
- To work properly, first name, last name and email must be set for the admin account

This example doesn't have a detailed installation like on the main branch, because at the opposite of the previously quoted example, the goal is not to provide a thorough installation but simply to present the full code presented in the talk. Moreover, Traefik configuration may be complex without knowledge of the tool and is out of the scope of this repository.