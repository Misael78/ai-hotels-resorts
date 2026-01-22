Constraints in the RegistrationConstraint folder represent constraints using
the RegistrationConstraint annotation instead of the standard Constraint
annotation defined by Drupal core. These constraints differ from standard
constraints in that they can only be executed by the special Registration
Validator invoked through the registration.validator service. This service
uses a special execution context that supports cacheable metadata. These
constraints can also define dependencies to ensure proper ordering of
constraint pipelines.

Note that the validators for these constraints translate the violation causes.
Symfony translates messages, but not causes, since causes can be any arbitrary
values and not necessarily translatable text. Since the registration module
uses the causes as translatable text, translation of the causes is done
explicitly to support multilingual applications.
