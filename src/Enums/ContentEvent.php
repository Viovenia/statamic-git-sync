<?php

declare(strict_types=1);

namespace Viovenia\GitSync\Enums;

/**
 * The Statamic events this addon can react to.
 *
 * The case value is the key used in `config/statamic-git-sync.php`; the
 * class name is the event Statamic dispatches. Not every event exists in
 * every Statamic major, which is why `available()` filters by class_exists
 * instead of assuming.
 */
enum ContentEvent: string
{
    case EntrySaved = 'entry_saved';
    case EntryDeleted = 'entry_deleted';
    case TermSaved = 'term_saved';
    case TermDeleted = 'term_deleted';
    case TaxonomySaved = 'taxonomy_saved';
    case TaxonomyDeleted = 'taxonomy_deleted';
    case GlobalSetSaved = 'global_set_saved';
    case GlobalSetDeleted = 'global_set_deleted';
    case GlobalVariablesSaved = 'global_variables_saved';
    case NavSaved = 'nav_saved';
    case NavDeleted = 'nav_deleted';
    case NavTreeSaved = 'nav_tree_saved';
    case AssetSaved = 'asset_saved';
    case AssetDeleted = 'asset_deleted';
    case AssetFolderSaved = 'asset_folder_saved';
    case AssetFolderDeleted = 'asset_folder_deleted';
    case AssetContainerSaved = 'asset_container_saved';
    case AssetContainerDeleted = 'asset_container_deleted';
    case CollectionSaved = 'collection_saved';
    case CollectionDeleted = 'collection_deleted';
    case CollectionTreeSaved = 'collection_tree_saved';
    case BlueprintSaved = 'blueprint_saved';
    case BlueprintDeleted = 'blueprint_deleted';
    case FieldsetSaved = 'fieldset_saved';
    case FieldsetDeleted = 'fieldset_deleted';
    case FormSaved = 'form_saved';
    case FormDeleted = 'form_deleted';
    case SubmissionSaved = 'submission_saved';
    case SubmissionDeleted = 'submission_deleted';
    case UserSaved = 'user_saved';
    case UserDeleted = 'user_deleted';
    case UserGroupSaved = 'user_group_saved';
    case UserGroupDeleted = 'user_group_deleted';
    case RoleSaved = 'role_saved';
    case RoleDeleted = 'role_deleted';
    case SiteSaved = 'site_saved';
    case SiteDeleted = 'site_deleted';

    /** @return class-string */
    public function eventClass(): string
    {
        return match ($this) {
            self::EntrySaved => \Statamic\Events\EntrySaved::class,
            self::EntryDeleted => \Statamic\Events\EntryDeleted::class,
            self::TermSaved => \Statamic\Events\TermSaved::class,
            self::TermDeleted => \Statamic\Events\TermDeleted::class,
            self::TaxonomySaved => \Statamic\Events\TaxonomySaved::class,
            self::TaxonomyDeleted => \Statamic\Events\TaxonomyDeleted::class,
            self::GlobalSetSaved => \Statamic\Events\GlobalSetSaved::class,
            self::GlobalSetDeleted => \Statamic\Events\GlobalSetDeleted::class,
            self::GlobalVariablesSaved => \Statamic\Events\GlobalVariablesSaved::class,
            self::NavSaved => \Statamic\Events\NavSaved::class,
            self::NavDeleted => \Statamic\Events\NavDeleted::class,
            self::NavTreeSaved => \Statamic\Events\NavTreeSaved::class,
            self::AssetSaved => \Statamic\Events\AssetSaved::class,
            self::AssetDeleted => \Statamic\Events\AssetDeleted::class,
            self::AssetFolderSaved => \Statamic\Events\AssetFolderSaved::class,
            self::AssetFolderDeleted => \Statamic\Events\AssetFolderDeleted::class,
            self::AssetContainerSaved => \Statamic\Events\AssetContainerSaved::class,
            self::AssetContainerDeleted => \Statamic\Events\AssetContainerDeleted::class,
            self::CollectionSaved => \Statamic\Events\CollectionSaved::class,
            self::CollectionDeleted => \Statamic\Events\CollectionDeleted::class,
            self::CollectionTreeSaved => \Statamic\Events\CollectionTreeSaved::class,
            self::BlueprintSaved => \Statamic\Events\BlueprintSaved::class,
            self::BlueprintDeleted => \Statamic\Events\BlueprintDeleted::class,
            self::FieldsetSaved => \Statamic\Events\FieldsetSaved::class,
            self::FieldsetDeleted => \Statamic\Events\FieldsetDeleted::class,
            self::FormSaved => \Statamic\Events\FormSaved::class,
            self::FormDeleted => \Statamic\Events\FormDeleted::class,
            self::SubmissionSaved => \Statamic\Events\SubmissionSaved::class,
            self::SubmissionDeleted => \Statamic\Events\SubmissionDeleted::class,
            self::UserSaved => \Statamic\Events\UserSaved::class,
            self::UserDeleted => \Statamic\Events\UserDeleted::class,
            self::UserGroupSaved => \Statamic\Events\UserGroupSaved::class,
            self::UserGroupDeleted => \Statamic\Events\UserGroupDeleted::class,
            self::RoleSaved => \Statamic\Events\RoleSaved::class,
            self::RoleDeleted => \Statamic\Events\RoleDeleted::class,
            self::SiteSaved => \Statamic\Events\SiteSaved::class,
            self::SiteDeleted => \Statamic\Events\SiteDeleted::class,
        };
    }

    /** Whether the installed Statamic version actually ships this event. */
    public function available(): bool
    {
        return class_exists($this->eventClass());
    }

    /**
     * Every event the installed Statamic version provides.
     *
     * @return list<self>
     */
    public static function availableCases(): array
    {
        return array_values(array_filter(
            self::cases(),
            static fn (self $event): bool => $event->available(),
        ));
    }
}
