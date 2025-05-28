<?php

namespace App\traits;

trait AutoHideRelationIds {

    /**
     * Override toArray to automatically adjust hidden and appends based on loaded relations
     */
    public function toArray(): array {
        $this->adjustAttributesForLoadedRelations();
        return parent::toArray();
    }

    /**
     * Ajusta automáticamente los atributos hidden y appends según las relaciones cargadas
     */
    protected function adjustAttributesForLoadedRelations(): void {
        $originalHidden = $this->getOriginalHidden();
        $originalAppends = $this->getOriginalAppends();

        $hidden = $originalHidden;
        $appends = $originalAppends;

        // Recorrer todas las relaciones definidas en el modelo
        foreach ($this->getLoadedRelations() as $relationName => $relation) {
            // Si la relación está cargada, removerla de hidden y remover su _ids de appends
            $hidden = array_filter($hidden, fn($item) => $item !== $relationName);
            $appends = array_filter($appends, fn($item) => $item !== "{$relationName}_ids");

            // Si es una colección de modelos, aplicar el mismo ajuste a cada elemento
            if (is_iterable($relation)) {
                foreach ($relation as $relatedModel) {
                    if ($relatedModel && method_exists($relatedModel, 'adjustAttributesForLoadedRelations') && in_array(AutoHideRelationIds::class, class_uses($relatedModel))) {
                        $relatedModel->adjustAttributesForLoadedRelations();
                    }
                }
            } elseif ($relation && method_exists($relation, 'adjustAttributesForLoadedRelations') && in_array(AutoHideRelationIds::class, class_uses($relation))) {
                // Si es un modelo individual
                $relation->adjustAttributesForLoadedRelations();
            }
        }

        $this->setHidden($hidden);
        $this->setAppends($appends);
    }

    /**
     * Obtiene los atributos hidden originales definidos en el modelo
     */
    protected function getOriginalHidden(): array {
        return $this->hidden ?? [];
    }

    /**
     * Obtiene los atributos appends originales definidos en el modelo
     */
    protected function getOriginalAppends(): array {
        return $this->appends ?? [];
    }

    /**
     * Obtiene todas las relaciones cargadas
     */
    protected function getLoadedRelations(): array {
        return $this->relations ?? [];
    }
}
